<?php
/**
 * Admin sidebar - built from config/modules.php.
 * A module appears only if the user has "<module>.view". Pages not built yet show "coming soon".
 */
$reg = config('modules');
$current = Helpers::currentPath();

$groups = [];
foreach ($reg['modules'] as $module => $def) {
    if (!can($module . '.view') && $module !== 'dashboard') {
        continue;
    }
    $groups[$def['group']][$module] = $def;
}
$isActive = static function (?string $url) use ($current): bool {
    if ($url === null) {
        return false;
    }
    return $url === 'admin' ? $current === 'admin' : ($current === $url || str_starts_with($current, $url . '/'));
};
?>
<aside class="offcanvas-lg offcanvas-start admin-sidebar bg-white border-end" tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarLabel">
    <div class="offcanvas-header d-lg-none border-bottom">
        <h5 class="offcanvas-title brand-text fw-bold" id="adminSidebarLabel"><i class="bi bi-heart-pulse-fill"></i> <?= e(__('app_name')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 d-block">
        <nav class="py-2 w-100">
            <?php foreach ($groups as $g => $modules): ?>
                <?php if ($g !== 'main'): ?>
                    <div class="sidebar-heading"><?= e(tr($reg['groups'][$g][0] ?? $g, $reg['groups'][$g][1] ?? $g)) ?></div>
                <?php endif; ?>
                <?php foreach ($modules as $module => $def):
                    $label = tr($def['bn'], $def['en']); ?>
                    <?php if ($def['url'] !== null): ?>
                        <a class="sidebar-link <?= $isActive($def['url']) ? 'active' : '' ?>" href="<?= e(url($def['url'])) ?>">
                            <i class="bi <?= e($def['icon']) ?>"></i><span><?= e($label) ?></span>
                        </a>
                    <?php else: ?>
                        <span class="sidebar-link disabled" title="<?= e(__('coming_soon')) ?>">
                            <i class="bi <?= e($def['icon']) ?>"></i><span><?= e($label) ?></span>
                            <span class="badge bg-light text-muted ms-auto fw-normal"><?= e(__('coming_soon')) ?></span>
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
    </div>
</aside>
