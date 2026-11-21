<?php
declare(strict_types=1);

/** Settings → SMS, Email, Notifications (per event SMS / email switches), test messages and the message log. */
class AdminMessagingController extends Controller
{
    private const SMS = [
        'sms_enabled' => 'bool', 'sms_driver' => 'string', 'sms_api_url' => 'string', 'sms_api_method' => 'string',
        'sms_api_key' => 'string', 'sms_sender_id' => 'string', 'sms_success_keyword' => 'string',
    ];
    private const MAIL = [
        'mail_enabled' => 'bool', 'mail_driver' => 'string', 'mail_host' => 'string', 'mail_port' => 'int', 'mail_username' => 'string',
        'mail_password' => 'string', 'mail_encryption' => 'string', 'mail_from_address' => 'string', 'mail_from_name' => 'string',
    ];

    private function values(array $keys): array
    {
        $v = [];
        foreach ($keys as $k => $type) {
            $s = setting($k, '');
            $v[$k] = $type === 'bool' ? ($s ? '1' : '') : (string) $s;
        }
        return $v;
    }

    private function page(string $view, string $tab, array $keys, array $extra = []): void
    {
        $old = $_SESSION['_old'] ?? null;
        $values = $this->values($keys);
        foreach (['sms_api_key', 'mail_password'] as $secret) {
            if (isset($values[$secret])) {
                $values[$secret . '_set'] = $values[$secret] !== '' ? '1' : '';
                $values[$secret] = '';
            }
        }
        $this->view($view, [
            'title'  => __('settings'),
            'tab'    => $tab,
            'input'  => is_array($old) ? array_merge($values, $old) : $values,
            'errors' => $_SESSION['_errors'] ?? [],
            'recent' => $this->db->fetchAll("SELECT * FROM message_logs WHERE channel = :c ORDER BY id DESC LIMIT 5", ['c' => $tab === 'sms' ? 'sms' : 'email']),
        ] + $extra, 'admin');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function sms(): void
    {
        $this->page('admin/settings/sms', 'sms', self::SMS);
    }

    public function email(): void
    {
        $this->page('admin/settings/email', 'email', self::MAIL);
    }

    private function fail(array $errors, array $in, string $path): void
    {
        $_SESSION['_errors'] = $errors;
        unset($in['sms_api_key'], $in['mail_password']);
        Helpers::setOld($in);
        flash('error', __('form_has_errors'));
        redirect($path);
    }

    public function saveSms(): void
    {
        $in = $this->postData();
        $errors = $this->validate($in, [
            'sms_driver'          => 'required|in:' . implode(',', Messenger::SMS_DRIVERS),
            'sms_api_method'      => 'required|in:GET,POST',
            'sms_api_url'         => 'max:500',
            'sms_api_key'         => 'max:255',
            'sms_sender_id'       => 'max:30',
            'sms_success_keyword' => 'max:60',
        ]);
        if (($in['sms_driver'] ?? '') === 'http' && !preg_match('#^https?://\S+$#i', (string) ($in['sms_api_url'] ?? ''))) {
            $errors['sms_api_url'] = __('sms_url_required');
        }
        if ($errors) {
            $this->fail($errors, $in, 'admin/settings/sms');
        }
        $values = [];
        foreach (self::SMS as $k => $type) {
            if ($k === 'sms_api_key' && trim((string) ($in[$k] ?? '')) === '' && empty($in['clear_sms_api_key'])) {
                continue; // keep the saved key when the field is left empty
            }
            $values[$k] = [$type === 'bool' ? (!empty($in[$k]) ? '1' : '0') : trim((string) ($in[$k] ?? '')), 'sms', $type];
        }
        Setting::saveMany($values);
        flash('success', __('settings_saved'));
        redirect('admin/settings/sms');
    }

    public function saveEmail(): void
    {
        $in = $this->postData();
        $errors = $this->validate($in, [
            'mail_driver'       => 'required|in:' . implode(',', Messenger::MAIL_DRIVERS),
            'mail_host'         => 'max:190',
            'mail_port'         => 'integer|gte:1|lte:65535',
            'mail_username'     => 'max:190',
            'mail_password'     => 'max:255',
            'mail_encryption'   => 'required|in:tls,ssl,none',
            'mail_from_address' => 'email|max:190',
            'mail_from_name'    => 'max:100',
        ]);
        if (($in['mail_driver'] ?? '') === 'smtp' && trim((string) ($in['mail_host'] ?? '')) === '') {
            $errors['mail_host'] = __('v_required');
        }
        if (($in['mail_driver'] ?? '') !== 'log' && trim((string) ($in['mail_from_address'] ?? '')) === '') {
            $errors['mail_from_address'] = __('v_required');
        }
        if ($errors) {
            $this->fail($errors, $in, 'admin/settings/email');
        }
        $values = [];
        foreach (self::MAIL as $k => $type) {
            if ($k === 'mail_password' && (string) ($in[$k] ?? '') === '' && empty($in['clear_mail_password'])) {
                continue;
            }
            $v = $type === 'bool' ? (!empty($in[$k]) ? '1' : '0') : ($k === 'mail_password' ? (string) ($in[$k] ?? '') : trim((string) ($in[$k] ?? '')));
            $values[$k] = [$v, 'mail', $type];
        }
        Setting::saveMany($values);
        flash('success', __('settings_saved'));
        redirect('admin/settings/email');
    }

    public function test(): void
    {
        $channel = ($_POST['channel'] ?? '') === 'email' ? 'email' : 'sms';
        $to = trim((string) ($_POST['to'] ?? ''));
        $back = 'admin/settings/' . $channel;
        if ($channel === 'sms' && !Auth::isValidBdPhone($to)) {
            flash('error', __('v_phone'));
            redirect($back);
        }
        if ($channel === 'email' && !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('error', __('v_email'));
            redirect($back);
        }
        $id = $channel === 'sms'
            ? Messenger::sms($to, __('app_name') . ': ' . __('test_message_text'), 'test', null, true)
            : Messenger::email($to, __('test_message_subject'), '<p>' . e(__('test_message_text')) . '</p>', 'test', null, true);
        $m = $this->db->fetch('SELECT status, response FROM message_logs WHERE id = :id', ['id' => $id]);
        Audit::log('messages.test', 'message_logs', $id, null, ['channel' => $channel, 'status' => $m['status'] ?? null]);
        $type = ($m['status'] ?? '') === 'sent' ? 'success' : (($m['status'] ?? '') === 'logged' ? 'warning' : 'error');
        flash($type, __('test_result_' . ($m['status'] ?? 'failed')) . ' ' . mb_substr((string) ($m['response'] ?? ''), 0, 200));
        redirect($back);
    }

    public function notifications(): void
    {
        $this->view('admin/settings/notifications', [
            'title'  => __('settings'),
            'matrix' => Notifier::matrix(),
            'smsOn'  => Messenger::smsDriver() !== 'log',
            'mailOn' => Messenger::mailDriver() !== 'log',
        ], 'admin');
    }

    public function saveNotifications(): void
    {
        $set = [];
        foreach (Notifier::EVENTS as $e) {
            $set[$e] = ['sms' => !empty($_POST['ev'][$e]['sms']) ? 1 : 0, 'email' => !empty($_POST['ev'][$e]['email']) ? 1 : 0];
        }
        Setting::saveMany(['notify_events' => [json_encode($set), 'notify', 'json']]);
        flash('success', __('settings_saved'));
        redirect('admin/settings/notifications');
    }

    /* ---------------- message log ---------------- */

    public function log(): void
    {
        $channel = in_array(Helpers::str('channel'), ['sms', 'email'], true) ? Helpers::str('channel') : '';
        $status = in_array(Helpers::str('status'), ['queued', 'sent', 'failed', 'logged'], true) ? Helpers::str('status') : '';
        $q = mb_substr(Helpers::str('q'), 0, 60);
        $where = ['1 = 1'];
        $p = [];
        if ($channel !== '') {
            $where[] = 'channel = :c';
            $p['c'] = $channel;
        }
        if ($status !== '') {
            $where[] = 'status = :s';
            $p['s'] = $status;
        }
        if ($q !== '') {
            $where[] = '(recipient LIKE :q1 OR subject LIKE :q2 OR body LIKE :q3)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $p += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        $w = implode(' AND ', $where);
        $pager = new Paginator((int) $this->db->fetchColumn("SELECT COUNT(*) FROM message_logs WHERE {$w}", $p), 30);
        $this->view('admin/settings/messages', [
            'title'    => __('message_log'),
            'channel'  => $channel,
            'status'   => $status,
            'q'        => $q,
            'counts'   => array_column($this->db->fetchAll('SELECT status, COUNT(*) AS n FROM message_logs GROUP BY status'), 'n', 'status'),
            'messages' => $this->db->fetchAll("SELECT * FROM message_logs WHERE {$w} ORDER BY id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset()}", $p),
            'pager'    => $pager,
        ], 'admin');
    }

    /** Send again: one message (id) or every failed / queued one (all=1). */
    public function retry(): void
    {
        $ids = !empty($_POST['all'])
            ? array_map('intval', array_column($this->db->fetchAll("SELECT id FROM message_logs WHERE status IN ('failed','queued') ORDER BY id LIMIT 200"), 'id'))
            : [(int) ($_POST['id'] ?? 0)];
        $sent = 0;
        foreach (array_filter($ids) as $id) {
            $sent += Messenger::deliver($id) === 'sent' ? 1 : 0;
        }
        Audit::log('messages.retry', 'message_logs', null, null, ['ids' => count($ids), 'sent' => $sent]);
        flash('success', __('messages_retried', ['n' => num(count(array_filter($ids))), 'sent' => num($sent)]));
        back();
    }
}
