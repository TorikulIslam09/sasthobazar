<?php
/** Pagination links. Var: $p (Paginator) */
$start = max(1, $p->page - 2);
$end = min($p->pages, $p->page + 2);
?>
<nav aria-label="Pagination">
    <ul class="pagination pagination-sm flex-wrap mb-0">
        <li class="page-item <?= $p->page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e($p->url(max(1, $p->page - 1))) ?>" aria-label="Previous">&laquo;</a>
        </li>
        <?php if ($start > 1): ?>
            <li class="page-item"><a class="page-link" href="<?= e($p->url(1)) ?>"><?= e(num(1)) ?></a></li>
            <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
        <?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
            <li class="page-item <?= $i === $p->page ? 'active' : '' ?>"><a class="page-link" href="<?= e($p->url($i)) ?>"><?= e(num($i)) ?></a></li>
        <?php endfor; ?>
        <?php if ($end < $p->pages): ?>
            <?php if ($end < $p->pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
            <li class="page-item"><a class="page-link" href="<?= e($p->url($p->pages)) ?>"><?= e(num($p->pages)) ?></a></li>
        <?php endif; ?>
        <li class="page-item <?= $p->page >= $p->pages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e($p->url(min($p->pages, $p->page + 1))) ?>" aria-label="Next">&raquo;</a>
        </li>
    </ul>
</nav>
