<?php /** Reviews moderation. Vars: $reviews, $pager, $status, $rating, $q, $counts, $auto */
$stars = static fn(int $n) => str_repeat('★', $n) . '<span class="text-muted">' . str_repeat('☆', 5 - $n) . '</span>';
$canMod = can('reviews.moderate');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-star"></i> <?= e(__('reviews')) ?></h1>
    <?php if ($canMod): ?>
        <form method="post" action="<?= e(url('admin/reviews/settings')) ?>" class="d-flex align-items-center gap-2">
            <?= csrf_field() ?>
            <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" role="switch" name="review_auto_approve" value="1" id="raa" <?= $auto ? 'checked' : '' ?> onchange="this.form.submit()"><label class="form-check-label small" for="raa"><?= e(__('review_auto_approve')) ?></label></div>
            <noscript><button class="btn btn-sm btn-outline-secondary"><?= e(__('save')) ?></button></noscript>
        </form>
    <?php endif; ?>
</div>
<ul class="nav nav-pills small mb-3">
    <?php foreach (['' => __('all'), 'pending' => __('rv_pending'), 'approved' => __('rv_approved'), 'rejected' => __('rv_rejected')] as $k => $label): ?>
        <li class="nav-item"><a class="nav-link<?= $status === $k ? ' active' : '' ?>" href="<?= e(url('admin/reviews' . ($k !== '' ? '?status=' . $k : ''))) ?>"><?= e($label) ?><?php if ($k !== ''): ?> <span class="badge <?= $status === $k ? 'text-bg-light' : ($k === 'pending' && !empty($counts['pending']) ? 'text-bg-warning' : 'text-bg-secondary') ?>"><?= e(num((int) ($counts[$k] ?? 0))) ?></span><?php endif; ?></a></li>
    <?php endforeach; ?>
</ul>
<form method="get" class="d-flex flex-wrap gap-2 mb-3">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <input class="form-control form-control-sm" style="max-width: 260px" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('review_search_ph')) ?>" aria-label="<?= e(__('search')) ?>">
    <select class="form-select form-select-sm" style="max-width: 140px" name="rating" aria-label="<?= e(__('rating')) ?>">
        <option value=""><?= e(__('all_ratings')) ?></option>
        <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>" <?= $rating === $i ? 'selected' : '' ?>><?= str_repeat('★', $i) ?></option><?php endfor; ?>
    </select>
    <button class="btn btn-sm btn-brand"><i class="bi bi-search"></i></button>
</form>
<form method="post" action="<?= e(url('admin/reviews/moderate')) ?>" id="rvForm">
    <?= csrf_field() ?>
    <?php if ($canMod && $reviews): ?>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <button class="btn btn-success btn-sm" name="action" value="approve"><i class="bi bi-check2"></i> <?= e(__('approve_selected')) ?></button>
            <button class="btn btn-outline-warning btn-sm" name="action" value="reject"><i class="bi bi-x"></i> <?= e(__('reject_selected')) ?></button>
            <?php if (can('reviews.delete')): ?><button class="btn btn-outline-danger btn-sm" name="action" value="delete" onclick="return confirm('<?= e(__('reviews_delete_confirm')) ?>')"><i class="bi bi-trash"></i> <?= e(__('delete_selected')) ?></button><?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0 small">
                <thead class="table-light"><tr>
                    <th style="width: 32px"><?php if ($canMod): ?><input class="form-check-input" type="checkbox" aria-label="<?= e(__('select_all')) ?>" onclick="document.querySelectorAll('#rvForm [name=&quot;ids[]&quot;]').forEach(c => c.checked = this.checked)"><?php endif; ?></th>
                    <th><?= e(__('review')) ?></th><th><?= e(__('product')) ?></th><th><?= e(__('customer')) ?></th><th><?= e(__('status')) ?></th><th class="text-end"><?= e(__('actions')) ?></th>
                </tr></thead>
                <tbody>
                <?php if (!$reviews): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
                <?php foreach ($reviews as $r): $id = (int) $r['id']; ?>
                    <tr class="<?= $r['status'] === 'pending' ? 'table-warning' : '' ?>">
                        <td><?php if ($canMod): ?><input class="form-check-input" type="checkbox" name="ids[]" value="<?= $id ?>" aria-label="#<?= $id ?>"><?php endif; ?></td>
                        <td style="max-width: 380px">
                            <div class="text-warning"><?= $stars((int) $r['rating']) ?></div>
                            <?php if ($r['title']): ?><div class="fw-semibold"><?= e($r['title']) ?></div><?php endif; ?>
                            <div><?= e($r['comment'] ?? '') ?></div>
                            <div class="text-muted"><?= e(dt($r['created_at'], 'd M Y, h:i A')) ?><?= (int) $r['is_verified_purchase'] ? ' · <span class="text-success"><i class="bi bi-patch-check"></i> ' . e(__('verified_purchase')) . '</span>' : '' ?></div>
                        </td>
                        <td><a href="<?= e(url('product/' . $r['product_slug'])) ?>" target="_blank" rel="noopener"><?= e($r['product_name']) ?></a><?= $r['shop_name'] ? '<div class="text-muted">' . e($r['shop_name']) . '</div>' : '' ?></td>
                        <td><?= e($r['customer_name'] ?? '—') ?><div class="text-muted"><?= e($r['customer_phone'] ?? '') ?></div></td>
                        <td><span class="badge text-bg-<?= ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'secondary'][$r['status']] ?>"><?= e(__('rv_' . $r['status'])) ?></span></td>
                        <td class="text-end text-nowrap">
                            <?php if ($canMod): ?>
                                <?php if ($r['status'] !== 'approved'): ?><button class="btn btn-sm btn-outline-success" name="action" value="approve" onclick="this.form.querySelectorAll('[name=&quot;ids[]&quot;]').forEach(c => c.checked = c.value === '<?= $id ?>')" title="<?= e(__('approve')) ?>"><i class="bi bi-check2"></i></button><?php endif; ?>
                                <?php if ($r['status'] !== 'rejected'): ?><button class="btn btn-sm btn-outline-warning" name="action" value="reject" onclick="this.form.querySelectorAll('[name=&quot;ids[]&quot;]').forEach(c => c.checked = c.value === '<?= $id ?>')" title="<?= e(__('reject')) ?>"><i class="bi bi-x"></i></button><?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>
<div class="mt-3"><?= $pager->links() ?></div>
