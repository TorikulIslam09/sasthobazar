<?php /** Vars: $s, $d (items), $t */ $acc = 'faq' . (int) $s['id']; ?>
<div class="accordion" id="<?= $acc ?>">
    <?php foreach ($d as $i => $it): if ($t('q', $it) === '') { continue; } ?>
        <div class="accordion-item">
            <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $acc ?>-<?= $i ?>" aria-expanded="false"><?= e($t('q', $it)) ?></button></h3>
            <div id="<?= $acc ?>-<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#<?= $acc ?>"><div class="accordion-body small"><?= nl2br(e($t('a', $it))) ?></div></div>
        </div>
    <?php endforeach; ?>
</div>
