<?php /** Vars: $set, $d[items, wished] */ $more = !empty($set['view_all']) ? Menu::href((string) $set['view_all']) : null; ?>
<div class="<?= ($set['layout'] ?? 'scroll') === 'grid' ? 'sb-grid' : 'sb-row' ?>">
    <?php foreach ($d['items'] as $p): ?><?= View::partial('shop/_card', ['p' => $p, 'wished' => isset($d['wished'][(int) $p['id']]), 'progress' => null]) ?><?php endforeach; ?>
</div>
<?php if ($more): ?><div class="text-center mt-3"><a class="btn btn-outline-primary btn-sm" href="<?= e($more) ?>"><?= e(__('view_all')) ?> <i class="bi bi-arrow-right"></i></a></div><?php endif; ?>
