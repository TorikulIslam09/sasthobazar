<?php /** Tabs across the logistics pages. Var: $active */ ?>
<ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto">
    <?php foreach ([['shipments', 'admin/shipments', 'bi-send', 'shipments.view'], ['cod', 'admin/cod', 'bi-cash-stack', 'shipments.view'], ['couriers', 'admin/couriers', 'bi-bicycle', 'couriers.view'], ['shipping', 'admin/shipping', 'bi-truck', 'shipping.view']] as [$k, $u, $i, $perm]):
        if (!can($perm)) { continue; } ?>
        <li class="nav-item"><a class="nav-link text-nowrap <?= $active === $k ? 'active' : '' ?>" href="<?= e(url($u)) ?>"><i class="bi <?= $i ?>"></i> <?= e(__('lg_' . $k)) ?></a></li>
    <?php endforeach; ?>
</ul>
