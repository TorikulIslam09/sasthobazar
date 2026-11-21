<?php
declare(strict_types=1);

/**
 * Settings → Backup: create / download / delete database backups and restore one (upload or a saved file).
 * Restoring replaces the whole database, so it needs the admin's password and a typed confirmation word.
 */
class AdminBackupController extends Controller
{
    public function index(): void
    {
        $db = $this->db;
        $this->view('admin/settings/backup', [
            'title'   => __('settings'),
            'backups' => Backup::list(),
            'dbSize'  => (float) $db->fetchColumn('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.TABLES WHERE table_schema = DATABASE()'),
            'tables'  => (int) $db->fetchColumn("SELECT COUNT(*) FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'"),
            'maxUp'   => min(self::iniBytes((string) ini_get('upload_max_filesize')), self::iniBytes((string) ini_get('post_max_size'))),
            'cron'    => [
                'heartbeat' => setting('cron_last_heartbeat'),
                'backup'    => setting('cron_last_backup'),
                'auto'      => (bool) setting('backup_auto', true),
                'keep'      => (int) setting('backup_keep', 14),
            ],
        ], 'admin');
    }

    /** Automatic daily backup on / off and how many to keep (run by cron/cron.php). */
    public function schedule(): void
    {
        $keep = max(3, min(90, (int) ($_POST['backup_keep'] ?? 14)));
        Setting::saveMany([
            'backup_auto' => [!empty($_POST['backup_auto']) ? '1' : '0', 'backup', 'bool'],
            'backup_keep' => [(string) $keep, 'backup', 'int'],
        ]);
        flash('success', __('settings_saved'));
        redirect('admin/settings/backup');
    }

    private static function iniBytes(string $v): int
    {
        $n = (int) $v;
        return $n * match (strtolower(substr(trim($v), -1))) { 'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1 };
    }

    public function create(): void
    {
        try {
            $name = Backup::create('manual');
        } catch (Throwable $e) {
            flash('error', __('backup_failed') . ' ' . $e->getMessage());
            redirect('admin/settings/backup');
        }
        Audit::log('settings.backup_create', 'backups', null, null, ['file' => $name]);
        if (!empty($_POST['download'])) {
            $this->send($name);
        }
        flash('success', __('backup_created', ['file' => $name]));
        redirect('admin/settings/backup');
    }

    public function download(string $name): void
    {
        if (!Backup::path($name)) {
            $this->notFound();
        }
        Audit::log('settings.backup_download', 'backups', null, null, ['file' => $name]);
        $this->send($name);
    }

    private function send(string $name): void
    {
        $path = (string) Backup::path($name);
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-store');
        readfile($path);
        exit;
    }

    public function destroy(string $name): void
    {
        if (Backup::delete($name)) {
            Audit::log('settings.backup_delete', 'backups', null, ['file' => $name], null);
            flash('success', __('backup_deleted'));
        } else {
            flash('error', __('backup_not_found'));
        }
        redirect('admin/settings/backup');
    }

    public function restore(): void
    {
        $user = User::find((int) Auth::id());
        if (!$user || !password_verify((string) ($_POST['password'] ?? ''), (string) $user['password_hash'])) {
            flash('error', __('wrong_password'));
            redirect('admin/settings/backup');
        }
        if (strtoupper(trim((string) ($_POST['confirm'] ?? ''))) !== 'RESTORE') {
            flash('error', __('restore_type_confirm'));
            redirect('admin/settings/backup');
        }
        $file = null;
        $label = '';
        $saved = (string) ($_POST['saved'] ?? '');
        if ($saved !== '') {
            $file = Backup::path($saved);
            $label = $saved;
        } else {
            $up = $_FILES['backup'] ?? null;
            if (is_array($up) && ($up['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file((string) $up['tmp_name'])) {
                if (!preg_match('/\.sql(\.gz)?$/i', (string) $up['name'])) {
                    flash('error', __('backup_bad_file'));
                    redirect('admin/settings/backup');
                }
                $file = (string) $up['tmp_name'];
                $label = mb_substr(basename((string) $up['name']), 0, 120);
            } elseif (is_array($up) && in_array($up['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                flash('error', __('backup_too_large'));
                redirect('admin/settings/backup');
            }
        }
        if (!$file) {
            flash('error', __('backup_choose_file'));
            redirect('admin/settings/backup');
        }
        $by = (int) $user['id'];
        try {
            $result = Backup::restore($file);
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect('admin/settings/backup');
        }
        // the restored database may not contain this session's user any more
        Audit::log('settings.backup_restore', 'backups', null, null, ['file' => $label, 'statements' => $result['statements'], 'safety' => $result['safety']], $by);
        if (!$this->db->fetchColumn("SELECT 1 FROM users WHERE id = :id AND status = 'active' AND deleted_at IS NULL", ['id' => $by])) {
            Auth::logout();
            redirect('admin/login');
        }
        flash('success', __('backup_restored', ['file' => $label, 'safety' => $result['safety']]));
        redirect('admin/settings/backup');
    }
}
