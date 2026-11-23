<?php
/** Settings tabs. Vars: $active */
$tabs = [
    'general'       => ['admin/settings/general', 'bi-shop-window', __('set_general'), 'settings.view'],
    'orders'        => ['admin/settings/orders', 'bi-credit-card', __('set_orders_payments'), 'settings.view'],
    'checkout'      => ['admin/settings/checkout', 'bi-bag-check', __('set_checkout'), 'settings.view'],
    'watermark'     => ['admin/settings/watermark', 'bi-droplet-half', __('set_watermark'), 'settings.view'],
    'sms'           => ['admin/settings/sms', 'bi-chat-dots', __('set_sms'), 'settings.view'],
    'email'         => ['admin/settings/email', 'bi-envelope', __('set_email'), 'settings.view'],
    'notifications' => ['admin/settings/notifications', 'bi-bell', __('set_notifications'), 'settings.view'],
    'messages'      => ['admin/messages', 'bi-list-ul', __('message_log'), 'notifications.view'],
    'backup'        => ['admin/settings/backup', 'bi-database-down', __('set_backup'), 'settings.backup'],
    'password'      => ['admin/profile#password', 'bi-key', __('change_password'), null],
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-gear"></i> <?= e(__('settings')) ?></h1>
</div>
<ul class="nav nav-pills small mb-3 flex-nowrap overflow-auto">
    <?php foreach ($tabs as $k => [$path, $icon, $label, $perm]): if ($perm !== null && !can($perm)) { continue; } ?>
        <li class="nav-item"><a class="nav-link text-nowrap<?= $k === $active ? ' active' : '' ?>" href="<?= e(url($path)) ?>"><i class="bi <?= e($icon) ?>"></i> <?= e($label) ?></a></li>
    <?php endforeach; ?>
</ul>
