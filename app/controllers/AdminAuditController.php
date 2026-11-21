<?php
declare(strict_types=1);

/** Audit log viewer: filter, detail (old vs new values), CSV export. Read-only. */
class AdminAuditController extends Controller
{
    public function index(): void
    {
        $filters = $this->filters();
        $p = new Paginator(AuditLog::countFiltered($filters), 30);
        $this->view('admin/audit/index', [
            'title'       => __('audit_logs'),
            'logs'        => AuditLog::filtered($filters, $p->perPage, $p->offset()),
            'paginator'   => $p,
            'filters'     => $filters,
            'entityTypes' => AuditLog::entityTypes(),
            'users'       => AuditLog::usersWithLogs(),
        ], 'admin');
    }

    public function show(string $id): void
    {
        $log = AuditLog::findWithUser((int) $id);
        if (!$log) {
            $this->notFound();
        }
        $old = $log['old_values'] ? (json_decode($log['old_values'], true) ?: []) : [];
        $new = $log['new_values'] ? (json_decode($log['new_values'], true) ?: []) : [];
        $this->view('admin/audit/show', [
            'title'  => __('audit_logs') . ' #' . $log['id'],
            'log'    => $log,
            'old'    => $old,
            'new'    => $new,
            'fields' => array_values(array_unique(array_merge(array_keys($old), array_keys($new)))),
        ], 'admin');
    }

    public function export(): void
    {
        $filters = $this->filters();
        Audit::log('audit_logs.export', 'audit_logs', null, null, ['filters' => $filters]);
        $rows = (static function () use ($filters) {
            foreach (AuditLog::iterate($filters) as $r) {
                yield [$r['id'], $r['created_at'], $r['user_name'], $r['user_id'], $r['action'], $r['entity_type'],
                       $r['entity_id'], $r['ip_address'], $r['old_values'], $r['new_values'], $r['user_agent']];
            }
        })();
        Helpers::csvDownload('audit-log-' . date('Ymd-His') . '.csv',
            ['ID', 'Time', 'User', 'User ID', 'Action', 'Entity', 'Entity ID', 'IP', 'Old values', 'New values', 'User agent'],
            $rows
        );
    }

    private function filters(): array
    {
        return [
            'user_id'     => ctype_digit(Helpers::str('user_id')) ? (int) Helpers::str('user_id') : 0,
            'action'      => mb_substr(Helpers::str('action'), 0, 100),
            'entity_type' => mb_substr(Helpers::str('entity_type'), 0, 50),
            'entity_id'   => Helpers::str('entity_id'),
            'ip'          => mb_substr(Helpers::str('ip'), 0, 45),
            'from'        => Helpers::str('from'),
            'to'          => Helpers::str('to'),
        ];
    }
}
