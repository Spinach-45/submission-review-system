<?php
require_once __DIR__ . '/../includes/manuscripts.php';

$user = require_role('editor');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $decision = $_POST['decision'] ?? '';
    $comment = trim($_POST['comment'] ?? '');

    if ($decision === 'approve') {
        $ok = approve_manuscript($id, (int) $user['id'], $comment);
        flash($ok ? '稿件已通過。' : '只能審查「審查中」的稿件。', $ok ? 'success' : 'error');
    } elseif ($decision === 'reject') {
        $ok = reject_manuscript($id, (int) $user['id'], $comment);
        flash($ok ? '稿件已退回，狀態改為「編輯中」。' : '只能審查「審查中」的稿件。', $ok ? 'success' : 'error');
    } else {
        flash('未知的操作。', 'error');
    }
    redirect('editor/index.php');
}

// 編輯只能看到已送出的稿件（審查中、已通過），作者的草稿不公開
$m = find_manuscript($id);
if (!$m || $m['status'] === 'editing') {
    flash('找不到稿件。', 'error');
    redirect('editor/index.php');
}
$logs = review_logs($id);

$pageTitle = '審查：' . $m['title'];
require __DIR__ . '/../includes/header.php';
?>
<a class="back" href="<?= h(url('editor/index.php')) ?>">← 回到待審查清單</a>
<article class="card manuscript">
    <div class="meta">
        <span class="tag"><?= h($m['category']) ?></span>
        <?= status_badge($m['status']) ?>
        <span class="muted">作者：<?= h($m['author']) ?></span>
    </div>
    <h1><?= h($m['title']) ?></h1>
    <div class="content"><?= nl2br(h($m['content'])) ?></div>
</article>

<?php if ($m['status'] === 'reviewing'): ?>
<form method="post" class="card form review-form">
    <h2>審查決定</h2>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
    <label>審查意見（退回時建議填寫，作者會看到）
        <textarea name="comment" rows="4" placeholder="例如：第二段論點需要補充資料來源。"></textarea>
    </label>
    <div class="form-actions">
        <button type="submit" name="decision" value="reject" class="btn btn-danger"
                onclick="return confirm('確定退回這篇稿件？')">退回稿件</button>
        <button type="submit" name="decision" value="approve" class="btn btn-success"
                onclick="return confirm('確定通過這篇稿件？')">通過</button>
    </div>
</form>
<?php endif; ?>

<?php require __DIR__ . '/../includes/review_history.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
