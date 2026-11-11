<?php
require_once __DIR__ . '/../includes/manuscripts.php';

$user = require_role('author');
$m = find_own_manuscript((int) ($_GET['id'] ?? 0), (int) $user['id']);
if (!$m) {
    flash('找不到稿件。', 'error');
    redirect('author/index.php');
}
$logs = review_logs((int) $m['id']);

$pageTitle = $m['title'];
require __DIR__ . '/../includes/header.php';
?>
<a class="back" href="<?= h(url('author/index.php')) ?>">← 回到我的投稿</a>
<article class="card manuscript">
    <div class="meta">
        <span class="tag"><?= h($m['category']) ?></span>
        <?= status_badge($m['status']) ?>
    </div>
    <h1><?= h($m['title']) ?></h1>
    <div class="content"><?= nl2br(h($m['content'])) ?></div>

    <?php if ($m['status'] === 'editing'): ?>
        <div class="form-actions">
            <a class="btn" href="<?= h(url('author/edit.php?id=' . $m['id'])) ?>">編輯</a>
            <form method="post" action="<?= h(url('author/delete.php')) ?>" class="inline" onsubmit="return confirm('確定刪除這篇稿件？')">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <button class="btn btn-danger">刪除</button>
            </form>
            <form method="post" action="<?= h(url('author/submit.php')) ?>" class="inline" onsubmit="return confirm('送出後將無法修改，確定送出審查？')">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <button class="btn btn-primary">送出投稿</button>
            </form>
        </div>
    <?php else: ?>
        <p class="hint">此稿件<?= $m['status'] === 'reviewing' ? '審查中' : '已通過' ?>，無法再修改。</p>
    <?php endif; ?>
</article>

<?php require __DIR__ . '/../includes/review_history.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
