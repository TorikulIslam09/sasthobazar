<?php /** Message status badge. Var: $s */ ?>
<span class="badge text-bg-<?= ['sent' => 'success', 'failed' => 'danger', 'queued' => 'info', 'logged' => 'secondary'][$s] ?? 'light' ?>" title="<?= e(__('msg_st_' . $s . '_help')) ?>"><?= e(__('msg_st_' . $s)) ?></span>
