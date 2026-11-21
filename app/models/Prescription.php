<?php
declare(strict_types=1);

/**
 * Prescription files (image or PDF). Stored OUTSIDE the web root in storage/private/prescriptions/{Y}/{m}/
 * with random names, and only streamed through a controller after an ownership / permission check.
 */
class Prescription extends Model
{
    protected static string $table = 'prescriptions';

    public const TYPES = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'application/pdf' => 'pdf',
    ];

    public static function maxBytes(): int
    {
        return max(1, (int) setting('prescription_max_size_mb', 5)) * 1024 * 1024;
    }

    /** Allowed extensions from settings (jpg, jpeg, png, pdf ...). */
    private static function allowedExt(): array
    {
        $list = setting('prescription_allowed_types', ['jpg', 'jpeg', 'png', 'pdf']);
        $list = is_array($list) ? array_map('strtolower', $list) : ['jpg', 'jpeg', 'png', 'pdf'];
        if (in_array('jpg', $list, true) || in_array('jpeg', $list, true)) {
            $list[] = 'jpg';
        }
        return $list;
    }

    /** @return string|null translated error, or null when the file is acceptable */
    public static function validate(array $f): ?string
    {
        $name = mb_substr(basename((string) ($f['name'] ?? '')), 0, 80);
        $mb = round(self::maxBytes() / 1048576, 1);
        if (in_array($f['error'] ?? -1, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return __('upload_too_large', ['name' => $name, 'mb' => $mb]);
        }
        if (($f['error'] ?? -1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
            return __('upload_failed', ['name' => $name]);
        }
        if ((int) $f['size'] <= 0 || (int) $f['size'] > self::maxBytes()) {
            return __('upload_too_large', ['name' => $name, 'mb' => $mb]);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
        $ext = self::TYPES[$mime] ?? null;
        if ($ext === null || !in_array($ext, self::allowedExt(), true)) {
            return __('rx_bad_type', ['name' => $name]);
        }
        $raw = (string) file_get_contents($f['tmp_name']);
        if ($ext === 'pdf') {
            if (!str_starts_with($raw, '%PDF-') || preg_match('#/(JavaScript|JS|Launch|EmbeddedFile)\b#', $raw)) {
                return __('rx_bad_type', ['name' => $name]);
            }
        } else {
            $info = @getimagesize($f['tmp_name']);
            if ($info === false || ($info['mime'] ?? '') !== $mime || $info[0] < 1 || $info[0] > Upload::MAX_DIMENSION || $info[1] > Upload::MAX_DIMENSION) {
                return __('rx_bad_type', ['name' => $name]);
            }
        }
        if (preg_match('/<\?(php|=)|<script/i', $raw)) {
            return __('rx_bad_type', ['name' => $name]);
        }
        return null;
    }

    /** Store an already validated upload; returns the new prescription id. */
    /**
     * @param int|null $userId null = guest upload (meta guest_name / guest_phone identify it; linked to the account later)
     * @param array $meta patient_name, notes, doctor_name, guest_name, guest_phone, request_type, address, district_id, upazila_id
     */
    public static function store(array $f, ?int $userId, array $meta = []): int
    {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
        $ext = self::TYPES[$mime] ?? null;
        if ($ext === null) {
            throw new RuntimeException('Unsupported prescription type');
        }
        $rel = 'prescriptions/' . date('Y') . '/' . date('m');
        $dir = STORAGE_PATH . '/private/' . $rel;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create prescription directory');
        }
        $file = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $file)) {
            throw new RuntimeException('Cannot store prescription');
        }
        @chmod($dir . '/' . $file, 0640);
        return static::db()->insert('prescriptions', [
            'user_id'       => $userId,
            'file_path'     => $rel . '/' . $file,
            'original_name' => mb_substr(basename((string) $f['name']), 0, 255),
            'mime_type'     => $mime,
            'file_size'     => (int) $f['size'],
            'patient_name'  => $meta['patient_name'] ?? null,
            'notes'         => $meta['notes'] ?? null,
            'status'        => 'pending',
        ] + array_intersect_key($meta, array_flip(['doctor_name', 'guest_name', 'guest_phone', 'request_type', 'address', 'district_id', 'upazila_id', 'group_code'])));
    }

    /** Remove the file + row (used when order placement fails right after upload). */
    public static function discard(int $id): void
    {
        $row = self::find($id);
        if ($row) {
            $abs = self::absPath($row);
            if ($abs && is_file($abs)) {
                @unlink($abs);
            }
            static::db()->delete('prescriptions', 'id = :id', ['id' => $id]);
        }
    }

    public static function absPath(array $row): ?string
    {
        $rel = (string) $row['file_path'];
        if (!preg_match('#^prescriptions/\d{4}/\d{2}/[a-f0-9]{32}\.(jpg|png|webp|pdf)$#', $rel)) {
            return null;
        }
        return STORAGE_PATH . '/private/' . $rel;
    }

    /** The customer's reusable prescriptions (pending / approved, last 180 days). */
    public static function reusable(int $userId): array
    {
        return static::db()->fetchAll(
            "SELECT id, original_name, patient_name, status, created_at FROM prescriptions
              WHERE user_id = :u AND status IN ('pending','approved') AND created_at >= (NOW() - INTERVAL 180 DAY)
           ORDER BY id DESC LIMIT 10",
            ['u' => $userId]
        );
    }

    /* ---------------- "Order by prescription" requests ---------------- */

    public const MAX_FILES = 5;
    public const REQUEST_STATUSES = ['new', 'contacted', 'ordered', 'cancelled'];

    public static function requestsEnabled(): bool
    {
        return (bool) setting('prescription_order_enabled', true);
    }

    /**
     * Save one request (1-5 already validated files) and tell the pharmacy team.
     * @param array $data name, phone, email?, patient_name?, notes?, address?, district_id?, upazila_id?
     * @return string request code (RX...)
     */
    public static function createRequest(array $files, ?int $userId, array $data): string
    {
        $code = 'RX' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
        $meta = [
            'request_type'   => 'order_request',
            'group_code'     => $code,
            'patient_name'   => $data['patient_name'] ?: null,
            'notes'          => $data['notes'] ?: null,
            'address'        => $data['address'] ?: null,
            'district_id'    => $data['district_id'] ?: null,
            'upazila_id'     => $data['upazila_id'] ?: null,
            'guest_name'     => $data['name'],   // the contact for this request (also kept for signed-in customers)
            'guest_phone'    => $data['phone'],
        ];
        $ids = [];
        try {
            foreach ($files as $f) {
                $ids[] = self::store($f, $userId, $meta);
            }
            static::db()->query("UPDATE prescriptions SET request_status = 'new' WHERE group_code = :g", ['g' => $code]);
        } catch (Throwable $e) {
            foreach ($ids as $id) {
                self::discard($id);
            }
            throw $e;
        }
        Notification::toStaff(['prescriptions.view'], 'prescription',
            __('rx_req_staff_title', ['code' => $code]),
            $data['name'] . ' · ' . $data['phone'] . ($data['notes'] ? ' — ' . mb_strimwidth((string) $data['notes'], 0, 80, '…') : ''),
            url('admin/prescriptions?type=order_request&status=all'));
        $title = __('rx_req_customer_title', ['code' => $code]);
        if ($userId !== null) {
            Notification::send($userId, 'prescription', $title, __('rx_req_customer_msg'), url('account/prescriptions'), 'prescription');
        } else {
            Notifier::contact((string) $data['phone'], (string) ($data['email'] ?? ''), 'prescription', $title, __('rx_req_customer_msg'));
        }
        Audit::log('prescriptions.request', 'prescriptions', $ids[0] ?? null, null, ['code' => $code, 'files' => count($ids)], $userId);
        return $code;
    }

    /** All files of a request, in upload order. */
    public static function group(string $code): array
    {
        return static::db()->fetchAll('SELECT * FROM prescriptions WHERE group_code = :g ORDER BY id', ['g' => $code]);
    }

    /**
     * The customer's prescriptions for "My prescriptions": one row per request / checkout upload,
     * with the number of files and the order it belongs to.
     */
    public static function forUser(int $userId, int $limit, int $offset): array
    {
        return static::db()->fetchAll(
            "SELECT pr.*, COALESCE(pr.group_code, CONCAT('#', pr.id)) AS gkey,
                    (SELECT COUNT(*) FROM prescriptions x WHERE x.group_code = pr.group_code) AS files,
                    COALESCE(pr.order_no, (SELECT o.order_no FROM orders o WHERE o.prescription_id = pr.id ORDER BY o.id DESC LIMIT 1)) AS linked_order
               FROM prescriptions pr
              WHERE pr.user_id = :u AND (pr.group_code IS NULL OR pr.id = (SELECT MIN(y.id) FROM prescriptions y WHERE y.group_code = pr.group_code))
           ORDER BY pr.id DESC LIMIT {$limit} OFFSET {$offset}",
            ['u' => $userId]
        );
    }

    public static function countForUser(int $userId): int
    {
        return (int) static::db()->fetchColumn(
            'SELECT COUNT(*) FROM prescriptions pr WHERE pr.user_id = :u AND (pr.group_code IS NULL OR pr.id = (SELECT MIN(y.id) FROM prescriptions y WHERE y.group_code = pr.group_code))',
            ['u' => $userId]
        );
    }

    /** Send the file to the browser (inline) and exit. */
    public static function stream(array $row): void
    {
        $abs = self::absPath($row);
        if (!$abs || !is_file($abs)) {
            http_response_code(404);
            exit;
        }
        $mime = array_key_exists((string) $row['mime_type'], self::TYPES) ? $row['mime_type'] : 'application/octet-stream';
        $name = 'prescription-' . (int) $row['id'] . '.' . pathinfo($abs, PATHINFO_EXTENSION);
        header_remove('X-Frame-Options');
        header('X-Frame-Options: SAMEORIGIN');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($abs));
        header('Content-Disposition: inline; filename="' . $name . '"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        if ($mime !== 'application/pdf') { // Chrome will not show PDFs inside a sandboxed document
            header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
        }
        readfile($abs);
        exit;
    }
}
