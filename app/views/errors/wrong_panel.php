<?php
/** Logged in with an account of another panel. Vars: $guard (wanted), $current (user's panel), $next (login path) */
$user = Auth::user();
$home = ['admin' => 'admin', 'vendor' => 'vendor-panel', 'customer' => ''][$current];
?>
<div class="card border-0 shadow-sm text-start mx-auto" style="max-width: 480px">
    <div class="card-body p-4">
        <div class="text-center mb-3"><i class="bi bi-person-badge fs-1 text-primary"></i></div>
        <h1 class="h5 text-center"><?= e(__('wrong_panel_title')) ?></h1>
        <p class="small text-center text-muted"><?= e(__('wrong_panel_text', ['name' => $user['name'] ?? '', 'current' => __('panel_' . $current), 'wanted' => __('panel_' . $guard)])) ?></p>
        <form method="post" action="<?= e(url('logout')) ?>" class="d-grid gap-2">
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e($next) ?>">
            <button class="btn btn-brand"><i class="bi bi-box-arrow-right"></i> <?= e(__('wrong_panel_logout', ['wanted' => __('panel_' . $guard)])) ?></button>
            <a class="btn btn-outline-secondary" href="<?= e(url($home)) ?>"><?= e(__('wrong_panel_back', ['current' => __('panel_' . $current)])) ?></a>
        </form>
    </div>
</div>
