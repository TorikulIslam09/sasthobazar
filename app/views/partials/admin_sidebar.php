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
        <nav class="py-2 w-100" data-side-nav>
            <div class="px-3 pb-2">
                <input type="search" class="form-control form-control-sm" placeholder="<?= e(__('nav_search')) ?>" aria-label="<?= e(__('nav_search')) ?>" data-side-filter>
            </div>
            <?php foreach ($groups as $g => $modules):
                $groupActive = false;
                foreach ($modules as $def) { if ($isActive($def['url'])) { $groupActive = true; } } ?>
                <div class="sidebar-group" data-side-group="<?= e($g) ?>" <?= $groupActive ? 'data-active="1"' : '' ?>>
                <?php if ($g !== 'main'): ?>
                    <button type="button" class="sidebar-heading sidebar-toggle" aria-expanded="true" data-side-toggle>
                        <span><?= e(tr($reg['groups'][$g][0] ?? $g, $reg['groups'][$g][1] ?? $g)) ?></span><i class="bi bi-chevron-down"></i>
                    </button>
                <?php endif; ?>
                <div class="sidebar-items">
                <?php foreach ($modules as $module => $def):
                    $label = tr($def['bn'], $def['en']); ?>
                    <?php if ($def['url'] !== null): ?>
                        <a class="sidebar-link <?= $isActive($def['url']) ? 'active' : '' ?>" href="<?= e(url($def['url'])) ?>" data-side-item="<?= e(mb_strtolower($def['bn'] . ' ' . $def['en'])) ?>">
                            <i class="bi <?= e($def['icon']) ?>"></i><span><?= e($label) ?></span>
                        </a>
                    <?php else: ?>
                        <span class="sidebar-link disabled" title="<?= e(__('coming_soon')) ?>" data-side-item="<?= e(mb_strtolower($def['bn'] . ' ' . $def['en'])) ?>">
                            <i class="bi <?= e($def['icon']) ?>"></i><span><?= e($label) ?></span>
                            <span class="badge bg-light text-muted ms-auto fw-normal"><?= e(__('coming_soon')) ?></span>
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
                </div>
                </div>
            <?php endforeach; ?>
        </nav>
    </div>
</aside>
