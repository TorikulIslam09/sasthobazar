<?php
/**
 * SasthoBazar scheduled tasks - run every 5 minutes from cron (command line only):
 *
 *   0-59/5 * * * *  /usr/bin/php /home/USER/public_html/cron/cron.php >> /home/USER/public_html/storage/logs/cron.log 2>&1
 *   (Windows / XAMPP test:  C:\xampp\php\php.exe cron\cron.php)
 *
 * Options:  --task=NAME  run one task now (ignores its schedule)    --list  show tasks and when they last ran
 *
 * Tasks (each remembers its last run in the settings table and only runs when due):
 *   release_earnings  every 15 min  seller earnings whose hold period ended -> withdrawable balance
 *   messages          every 5 min   send queued SMS / email, retry failed ones (max 3 attempts)
 *   courier_sync      every 30 min  ask the couriers for parcel status (only when courier mock mode is off)
 *   expiry_digest     daily         tell stock staff how many batches expire soon
 *   backup            daily         database backup into storage/backups, keep the newest N (setting backup_keep, default 14)
 *   cleanup           daily         old rate-limit rows, read notifications > 180 days, message log > 180 days
 * A lock file stops two runs from overlapping.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('SKIP_INSTALL_CHECK', true);
require dirname(__DIR__) . '/core/bootstrap.php';
if (!IS_INSTALLED) {
    fwrite(STDERR, "Not installed yet.\n");
    exit(1);
}
@set_time_limit(600);
$db = Database::getInstance();

$tasks = [
    'release_earnings' => 900,
    'messages'         => 300,
    'courier_sync'     => 1800,
    'expiry_digest'    => 86400,
    'backup'           => 86400,
    'cleanup'          => 86400,
];

$last = static fn(string $task): int => (int) strtotime((string) setting('cron_last_' . $task, '1970-01-01 00:00:00'));
$mark = static function (string $task) use ($db): void {
    $key = 'cron_last_' . $task;
    $now = date('Y-m-d H:i:s');
    $db->query("INSERT INTO settings (`group`, `key`, `value`, `type`, `label`, `is_public`) VALUES ('cron', :k, :v, 'string', :l, 0)
                ON DUPLICATE KEY UPDATE value = VALUES(value)", ['k' => $key, 'v' => $now, 'l' => $key]);
};
$log = static fn(string $msg) => print('[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n");

$args = array_slice($argv, 1);
if (in_array('--list', $args, true)) {
    foreach ($tasks as $t => $every) {
        printf("%-18s every %5d min   last run: %s\n", $t, $every / 60, setting('cron_last_' . $t, 'never'));
    }
    exit(0);
}
$only = null;
foreach ($args as $a) {
    if (str_starts_with($a, '--task=')) {
        $only = substr($a, 7);
        if (!isset($tasks[$only])) {
            fwrite(STDERR, "Unknown task: {$only}\n");
            exit(1);
        }
    }
}

$lock = fopen(STORAGE_PATH . '/cron.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    $log('another run is still busy - skipped');
    exit(0);
}

$run = [
    'release_earnings' => static function () use ($db, $log): void {
        $vendors = $db->fetchAll("SELECT DISTINCT vendor_id FROM order_vendor_splits WHERE settlement_status = 'pending' AND release_at <= NOW() AND vendor_id IS NOT NULL");
        $total = 0.0;
        foreach ($vendors as $v) {
            $total += Wallet::releaseDue((int) $v['vendor_id']);
        }
        $log('release_earnings: ' . count($vendors) . ' seller(s), ' . number_format($total, 2) . ' released');
    },
    'messages' => static function () use ($db, $log): void {
        $ids = array_map('intval', array_column($db->fetchAll(
            "SELECT id FROM message_logs
              WHERE (status = 'queued' AND created_at < (NOW() - INTERVAL 2 MINUTE)) OR (status = 'failed' AND attempts < 3)
           ORDER BY id LIMIT 100"
        ), 'id'));
        $sent = 0;
        foreach ($ids as $id) {
            $sent += Messenger::deliver($id) === 'sent' ? 1 : 0;
        }
        $log('messages: ' . count($ids) . ' tried, ' . $sent . ' sent');
    },
    'courier_sync' => static function () use ($db, $log): void {
        if ((bool) setting('courier_mock_mode', true)) {
            $log('courier_sync: skipped (courier mock mode is on)');
            return;
        }
        $rows = $db->fetchAll(
            "SELECT id FROM shipments WHERE courier_id IS NOT NULL AND status IN ('booked','picked_up','in_transit','out_for_delivery','on_hold','returning')
                AND (last_synced_at IS NULL OR last_synced_at < (NOW() - INTERVAL 25 MINUTE)) ORDER BY last_synced_at IS NOT NULL, last_synced_at LIMIT 50"
        );
        $changed = 0;
        foreach ($rows as $r) {
            try {
                $changed += Shipment::sync((int) $r['id'], null) !== null ? 1 : 0;
            } catch (Throwable $e) {
                $log('courier_sync: shipment ' . $r['id'] . ' - ' . $e->getMessage());
            }
        }
        $log('courier_sync: ' . count($rows) . ' checked, ' . $changed . ' changed');
    },
    'expiry_digest' => static function () use ($db, $log): void {
        $days = (int) setting('expiry_alert_days', 90);
        $row = $db->fetch(
            "SELECT SUM(expiry_date < CURDATE()) AS expired, SUM(expiry_date >= CURDATE()) AS soon FROM inventory_batches
              WHERE status = 'active' AND quantity_available > 0 AND expiry_date IS NOT NULL AND expiry_date <= (CURDATE() + INTERVAL :d DAY)",
            ['d' => $days]
        );
        $expired = (int) ($row['expired'] ?? 0);
        $soon = (int) ($row['soon'] ?? 0);
        if ($expired + $soon > 0) {
            Notification::toStaff(['inventory.view'], 'stock', __('notif_expiry_digest', ['expired' => $expired, 'soon' => $soon, 'days' => $days]), null, url('admin/reports/expiry'));
        }
        $log("expiry_digest: {$expired} expired, {$soon} expiring within {$days} days");
    },
    'backup' => static function () use ($log): void {
        if (!(bool) setting('backup_auto', true)) {
            $log('backup: automatic backups are off');
            return;
        }
        $name = Backup::create('auto');
        $keep = max(3, (int) setting('backup_keep', 14));
        $autos = array_values(array_filter(Backup::list(), static fn($b) => str_contains($b['name'], '-auto-')));
        $removed = 0;
        foreach (array_slice($autos, $keep) as $old) {
            $removed += Backup::delete($old['name']) ? 1 : 0;
        }
        $log("backup: {$name} created, {$removed} old automatic backup(s) removed");
    },
    'cleanup' => static function () use ($db, $log): void {
        $a = $db->query('DELETE FROM rate_limits WHERE reset_at < (NOW() - INTERVAL 1 DAY)')->rowCount();
        $b = $db->query('DELETE FROM notifications WHERE read_at IS NOT NULL AND created_at < (NOW() - INTERVAL 180 DAY)')->rowCount();
        $c = $db->query("DELETE FROM message_logs WHERE created_at < (NOW() - INTERVAL 180 DAY) AND status IN ('sent','logged')")->rowCount();
        $log("cleanup: {$a} rate-limit rows, {$b} old notifications, {$c} old messages removed");
    },
];

$failed = 0;
foreach ($tasks as $task => $every) {
    if ($only !== null ? $task !== $only : (time() - $last($task) < $every - 30)) {
        continue;
    }
    try {
        $run[$task]();
        $mark($task);
    } catch (Throwable $e) {
        $failed++;
        $log($task . ' FAILED: ' . $e->getMessage());
        error_log('cron ' . $task . ': ' . $e->getMessage());
    }
}
$mark('heartbeat');
flock($lock, LOCK_UN);
fclose($lock);
exit($failed ? 1 : 0);
