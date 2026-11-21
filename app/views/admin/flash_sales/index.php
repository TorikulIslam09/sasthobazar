<?php /** Flash sales list. Vars: $sales, $pager */
$now = date('Y-m-d H:i:s');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-lightning-charge"></i> <?= e(__('flash_sales')) ?></h1>
    <?php if (can('flash_sales.create')): ?><a class="btn btn-brand btn-sm" href="<?= e(url('admin/flash-sales/create')) ?>"><i class="bi bi-plus-lg"></i> <?= e(__('add_flash_sale')) ?></a><?php endif; ?>
</div>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light"><tr>
                <th><?= e(__('flash_sale')) ?></th><th><?= e(__('schedule')) ?></th><th class="text-center"><?= e(__('products')) ?></th>
                <th class="text-center"><?= e(__('sold')) ?></th><th><?= e(__('cost_bearer')) ?></th><th><?= e(__('status')) ?></th><th class="text-end"><?= e(__('actions')) ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$sales): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($sales as $s):
                $state = !(int) $s['is_active'] ? [__('offer_paused'), 'secondary']
                    : ($s['ends_at'] <= $now ? [__('offer_expired'), 'dark'] : ($s['starts_at'] > $now ? [__('offer_scheduled'), 'info'] : [__('offer_running'), 'success'])); ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($s['banner']): ?><img src="<?= e(url($s['banner'])) ?>" alt="" style="height: 34px; border-radius: 4px"><?php endif; ?>
                            <span class="fw-semibold"><?= e(tr($s['title_bn'], $s['title_en'])) ?></span>
                        </div>
                    </td>
                    <td class="text-nowrap"><?= e(dt($s['starts_at'], 'd M y, h:i A')) ?><br><span class="text-muted">→ <?= e(dt($s['ends_at'], 'd M y, h:i A')) ?></span>
                        <?php if ($state[1] === 'success'): ?><div class="sb-countdown-mini" data-no-reload data-countdown="<?= e(date('c', strtotime($s['ends_at']))) ?>"><span data-cd-days hidden><b data-cd="d">0</b><?= e(__('days_short')) ?> </span><b data-cd="h">00</b>:<b data-cd="m">00</b>:<b data-cd="s">00</b></div><?php endif; ?>
                    </td>
                    <td class="text-center"><?= e(num($s['item_count'])) ?></td>
                    <td class="text-center"><?= e(num($s['sold'])) ?></td>
                    <td><?= e(OfferForm::fundedText($s['funded_by'], $s['vendor_share_percent'])) ?></td>
                    <td><span class="badge text-bg-<?= $state[1] ?>"><?= e($state[0]) ?></span></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/flash-sales/' . (int) $s['id'] . '/edit')) ?>" title="<?= e(__('edit')) ?>"><i class="bi bi-pencil"></i></a>
                        <?php if (can('flash_sales.delete')): ?>
                            <?= View::partial('partials/delete_button', ['action' => url('admin/flash-sales/' . (int) $s['id'] . '/delete'), 'message' => __('confirm_delete_named', ['name' => $s['title_en']])]) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3"><?= $pager->links() ?></div>
