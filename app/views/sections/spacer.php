<?php /** Vars: $set */ $h = max(8, min(200, (int) ($set['height'] ?? 32))); ?>
<div style="height:<?= $h ?>px" aria-hidden="true"><?php if (!empty($set['divider'])): ?><hr class="m-0" style="position:relative;top:<?= (int) ($h / 2) ?>px"><?php endif; ?></div>
