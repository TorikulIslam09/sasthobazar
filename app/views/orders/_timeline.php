<?php /** Order tracking timeline. Var: $timeline (OrderView::timeline) */ ?>
<ol class="sb-timeline">
    <?php foreach ($timeline as [$status, $label, $time, $state]): ?>
        <li class="is-<?= e($state) ?><?= $state === 'end' ? ' is-' . e($status) : '' ?>">
            <span class="dot"><i class="bi <?= $state === 'end' ? ($status === 'returned' ? 'bi-arrow-counterclockwise' : 'bi-x') : ($state === 'done' ? 'bi-check' : ($state === 'current' ? 'bi-circle-fill' : 'bi-circle')) ?>"></i></span>
            <span class="lbl"><?= e($label) ?></span>
            <?php if ($time): ?><time datetime="<?= e(date('c', strtotime($time))) ?>"><?= e(dt($time, 'd M, h:i A')) ?></time><?php endif; ?>
        </li>
    <?php endforeach; ?>
</ol>
