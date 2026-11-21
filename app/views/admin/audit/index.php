<?php /** Audit log list. Vars: $logs, $paginator, $filters, $entityTypes, $users */ ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-journal-text"></i> <?= e(__('audit_logs')) ?></h1>
    <?php if (can('audit_logs.export')): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/audit-logs/export') . '?' . http_build_query(array_filter($filters))) ?>"><i class="bi bi-download"></i> <?= e(__('export_csv')) ?></a>
    <?php endif; ?>
</div>

<form class="card border-0 shadow-sm mb-3" method="get">
    <div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label small mb-1"><?= e(__('user')) ?></label>
            <select class="form-select form-select-sm" name="user_id">
                <option value=""><?= e(__('all')) ?></option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= $filters['user_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label small mb-1"><?= e(__('action')) ?></label>
            <input class="form-control form-control-sm" name="action" value="<?= e($filters['action']) ?>" placeholder="login, users.update">
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label small mb-1"><?= e(__('entity')) ?></label>
            <select class="form-select form-select-sm" name="entity_type">
                <option value=""><?= e(__('all')) ?></option>
                <?php foreach ($entityTypes as $t): ?>
                    <option value="<?= e($t) ?>" <?= $filters['entity_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-1">
            <label class="form-label small mb-1">ID</label>
            <input class="form-control form-control-sm" name="entity_id" inputmode="numeric" value="<?= e($filters['entity_id']) ?>">
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label small mb-1"><?= e(__('from_date')) ?></label>
            <input class="form-control form-control-sm" type="date" name="from" value="<?= e($filters['from']) ?>">
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label small mb-1"><?= e(__('to_date')) ?></label>
            <input class="form-control form-control-sm" type="date" name="to" value="<?= e($filters['to']) ?>">
        </div>
        <div class="col-12 col-md-6 col-xl-1 d-flex gap-1">
            <button class="btn btn-sm btn-brand flex-fill"><i class="bi bi-funnel"></i></button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/audit-logs')) ?>" title="<?= e(__('reset')) ?>"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th><?= e(__('time')) ?></th>
                    <th><?= e(__('user')) ?></th>
                    <th><?= e(__('action')) ?></th>
                    <th><?= e(__('entity')) ?></th>
                    <th class="d-none d-md-table-cell"><?= e(__('ip_address')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$logs): ?>
                <tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $l):
                $color = str_ends_with($l['action'], '.delete') || str_contains($l['action'], 'failed') || str_contains($l['action'], 'locked') || str_contains($l['action'], 'throttled')
                    ? 'danger' : (str_ends_with($l['action'], '.create') ? 'success' : (str_ends_with($l['action'], '.update') || str_contains($l['action'], 'permissions') ? 'primary' : 'secondary')); ?>
                <tr>
                    <td class="text-muted"><?= (int) $l['id'] ?></td>
                    <td class="text-nowrap"><?= e(dt($l['created_at'], 'd M Y, h:i:s A')) ?></td>
                    <td><?= $l['user_name'] !== null ? e($l['user_name']) : '<span class="text-muted">' . e(__('system')) . '</span>' ?></td>
                    <td><span class="badge bg-<?= $color ?>-subtle text-<?= $color ?>-emphasis"><?= e($l['action']) ?></span></td>
                    <td><?= e($l['entity_type'] ?? '—') ?><?= $l['entity_id'] ? ' #' . (int) $l['entity_id'] : '' ?></td>
                    <td class="d-none d-md-table-cell font-monospace"><?= e($l['ip_address']) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-secondary py-0" href="<?= e(url('admin/audit-logs/' . $l['id'])) ?>"><?= e(__('details')) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small text-muted"><?= e(__('showing', ['from' => num($paginator->from()), 'to' => num($paginator->to()), 'total' => num($paginator->total)])) ?></span>
        <?= $paginator->links() ?>
    </div>
</div>
