<?php /** Ticket queue. Vars: $tickets, $pager, $f, $counts */
$active = (int) ($counts['open'] ?? 0) + (int) ($counts['in_progress'] ?? 0) + (int) ($counts['waiting_customer'] ?? 0);
$tab = static function (string $st, string $label, int $n) use ($f): string {
    $on = ($f['status'] === $st) || ($st === 'active' && $f['status'] === '');
    return '<li class="nav-item"><a class="nav-link' . ($on ? ' active' : '') . '" href="' . e(url('admin/support?status=' . $st)) . '">' . e($label) . ' <span class="badge ' . ($on ? 'text-bg-light' : 'text-bg-secondary') . '">' . e(num($n)) . '</span></a></li>';
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-headset"></i> <?= e(__('support_tickets')) ?></h1>
    <div class="d-flex gap-2">
        <?php if (can('refunds.view')): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/refunds')) ?>"><i class="bi bi-arrow-counterclockwise"></i> <?= e(__('returns_refunds')) ?></a><?php endif; ?>
        <?php if (can('reviews.view')): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/reviews')) ?>"><i class="bi bi-star"></i> <?= e(__('reviews')) ?></a><?php endif; ?>
        <?php if (can('support.export')): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/support/export')) ?>"><i class="bi bi-download"></i> CSV</a><?php endif; ?>
    </div>
</div>
<ul class="nav nav-pills small mb-3 flex-nowrap overflow-auto">
    <?= $tab('active', __('tk_active'), $active) ?>
    <?php foreach (Support::STATUSES as $st): ?><?= $tab($st, __('tk_' . $st), (int) ($counts[$st] ?? 0)) ?><?php endforeach; ?>
</ul>
<form method="get" class="d-flex flex-wrap gap-2 mb-3">
    <input type="hidden" name="status" value="<?= e($f['status']) ?>">
    <input class="form-control form-control-sm" style="max-width: 260px" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(__('ticket_search_ph')) ?>" aria-label="<?= e(__('search')) ?>">
    <select class="form-select form-select-sm" style="max-width: 170px" name="category" aria-label="<?= e(__('category')) ?>">
        <option value=""><?= e(__('all_topics')) ?></option>
        <?php foreach (Support::CATEGORIES as $c): ?><option value="<?= $c ?>" <?= $f['category'] === $c ? 'selected' : '' ?>><?= e(__('tkc_' . $c)) ?></option><?php endforeach; ?>
    </select>
    <select class="form-select form-select-sm" style="max-width: 150px" name="priority" aria-label="<?= e(__('priority')) ?>">
        <option value=""><?= e(__('all_priorities')) ?></option>
        <?php foreach (Support::PRIORITIES as $p): ?><option value="<?= $p ?>" <?= $f['priority'] === $p ? 'selected' : '' ?>><?= e(__('tkp_' . $p)) ?></option><?php endforeach; ?>
    </select>
    <div class="form-check align-self-center"><input class="form-check-input" type="checkbox" name="mine" value="1" id="mine" <?= $f['mine'] ? 'checked' : '' ?>><label class="form-check-label small" for="mine"><?= e(__('assigned_to_me')) ?></label></div>
    <button class="btn btn-sm btn-brand"><i class="bi bi-search"></i></button>
</form>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light"><tr><th><?= e(__('ticket')) ?></th><th><?= e(__('customer')) ?></th><th><?= e(__('category')) ?></th><th><?= e(__('priority')) ?></th><th><?= e(__('status')) ?></th><th><?= e(__('assigned_to')) ?></th><th><?= e(__('last_activity_col')) ?></th></tr></thead>
            <tbody>
            <?php if (!$tickets): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('no_tickets')) ?></td></tr><?php endif; ?>
            <?php foreach ($tickets as $t): ?>
                <tr>
                    <td><a class="fw-semibold" href="<?= e(url('admin/support/' . (int) $t['id'])) ?>">#<?= e($t['ticket_no']) ?></a><div class="text-truncate" style="max-width: 280px"><?= e($t['subject']) ?></div></td>
                    <td><?= e($t['customer_name'] ?? '—') ?><div class="text-muted"><?= e($t['customer_phone'] ?? '') ?><?= $t['order_no'] ? ' · #' . e($t['order_no']) : '' ?></div></td>
                    <td><?= e(__('tkc_' . $t['category'])) ?></td>
                    <td><?= Support::priorityBadge($t['priority']) ?></td>
                    <td><?= Support::badge($t['status']) ?></td>
                    <td><?= e($t['assignee_name'] ?? '—') ?></td>
                    <td class="text-nowrap"><?= e(dt($t['last_reply_at'] ?? $t['created_at'], 'd M, h:i A')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3"><?= $pager->links() ?></div>
