<?php /** @var array $logs */ ?>
<?php if ($logs): ?>
<section class="card">
    <h2>審查歷程</h2>
    <ol class="timeline">
        <?php foreach ($logs as $log): ?>
            <li class="timeline-<?= h($log['action']) ?>">
                <div><strong><?= h(ACTION_LABELS[$log['action']]) ?></strong> · <?= h($log['display_name']) ?>
                    <span class="muted"><?= h(substr($log['created_at'], 0, 16)) ?></span></div>
                <?php if ($log['comment']): ?><div class="comment"><?= nl2br(h($log['comment'])) ?></div><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</section>
<?php endif; ?>
