<?php
require_once __DIR__ . '/../includes/manuscripts.php';

require_role('editor');
$items = pending_manuscripts();

$pageTitle = '待審查清單';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>待審查清單</h1>
    <span class="count">共 <?= count($items) ?> 篇</span>
</div>

<?php if (!$items): ?>
    <div class="empty">目前沒有待審查的稿件。</div>
<?php else: ?>
<div class="table-wrap">
<table class="table">
    <thead>
        <tr><th>作者</th><th>類別</th><th>標題</th><th>送出時間</th><th class="actions">操作</th></tr>
    </thead>
    <tbody>
    <?php foreach ($items as $m): ?>
        <tr>
            <td data-label="作者"><?= h($m['author']) ?></td>
            <td data-label="類別"><?= h($m['category']) ?></td>
            <td data-label="標題"><a href="<?= h(url('editor/review.php?id=' . $m['id'])) ?>"><?= h($m['title']) ?></a></td>
            <td data-label="送出"><?= h(substr((string) $m['submitted_at'], 0, 16)) ?></td>
            <td class="actions"><a class="btn btn-sm btn-primary" href="<?= h(url('editor/review.php?id=' . $m['id'])) ?>">審查</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
