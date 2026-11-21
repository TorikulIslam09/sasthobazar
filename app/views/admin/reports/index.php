<?php
/** Reports hub. */
$groups = [
    'reports'    => [__('reports'), 'reports.view', 'admin/reports/'],
    'accounting' => [__('accounting'), 'accounting.view', 'admin/accounting/'],
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-graph-up"></i> <?= e(__('reports')) ?></h1>
    <?php if (can('accounting.view')): ?><a class="btn btn-brand btn-sm" href="<?= e(url('admin/accounting/pnl')) ?>"><i class="bi bi-calculator"></i> <?= e(__('pl_title')) ?></a><?php endif; ?>
</div>
<?php foreach ($groups as $g => [$heading, $perm, $base]): if (!can($perm)) { continue; } ?>
    <h2 class="h6 text-muted text-uppercase mb-2"><?= e($heading) ?></h2>
    <div class="row g-3 mb-4">
        <?php foreach (Reports::LIST as $key => [$group, $icon]): if ($group !== $g) { continue; } ?>
            <div class="col-sm-6 col-xl-3">
                <a class="card card-link border-0 shadow-sm h-100 text-decoration-none" href="<?= e(url($base . $key)) ?>">
                    <div class="card-body d-flex gap-3 align-items-start">
                        <div class="stat-icon text-brand"><i class="bi <?= e($icon) ?>"></i></div>
                        <div class="min-w-0">
                            <div class="fw-semibold text-dark"><?= e(__('rp_' . $key)) ?></div>
                            <div class="small text-muted"><?= e(__('rp_' . $key . '_desc')) ?></div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
<p class="small text-muted"><i class="bi bi-info-circle"></i> <?= e(__('rp_export_help')) ?></p>
