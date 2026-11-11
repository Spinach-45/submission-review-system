<?php
require_once __DIR__ . '/../includes/manuscripts.php';

$user = require_role('author');
$status = $_GET['status'] ?? '';
$categoryId = (int) ($_GET['category'] ?? 0);
$items = author_manuscripts((int) $user['id'], $status ?: null, $categoryId ?: null);
$categories = categories();

$pageTitle = '我的投稿';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>我的投稿</h1>
    <a class="btn btn-primary" href="<?= h(url('author/edit.php')) ?>">＋ 新增投稿</a>
</div>

<form method="get" class="filters">
    <select name="status" onchange="this.form.submit()">
        <option value="">全部狀態</option>
        <?php foreach (STATUS_LABELS as $key => $label): ?>
            <option value="<?= h($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="category" onchange="this.form.submit()">
        <option value="">全部類別</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= $categoryId === (int) $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button class="btn">篩選</button></noscript>
</form>

<?php if (!$items): ?>
    <div class="empty">目前沒有稿件。</div>
<?php else: ?>
<div class="table-wrap">
<table class="table">
    <thead>
        <tr><th>類別</th><th>標題</th><th>審查狀態</th><th>最後更新</th><th class="actions">操作</th></tr>
    </thead>
    <tbody>
    <?php foreach ($items as $m): ?>
        <tr>
            <td data-label="類別"><?= h($m['category']) ?></td>
            <td data-label="標題"><a href="<?= h(url('author/view.php?id=' . $m['id'])) ?>"><?= h($m['title']) ?></a></td>
            <td data-label="狀態"><?= status_badge($m['status']) ?></td>
            <td data-label="更新"><?= h(substr($m['updated_at'], 0, 16)) ?></td>
            <td class="actions">
                <?php if ($m['status'] === 'editing'): ?>
                    <a class="btn btn-sm" href="<?= h(url('author/edit.php?id=' . $m['id'])) ?>">編輯</a>
                    <form method="post" action="<?= h(url('author/submit.php')) ?>" class="inline"
                          onsubmit="return confirm('送出後將無法修改，確定送出審查？')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                        <button class="btn btn-sm btn-primary">送出</button>
                    </form>
                    <form method="post" action="<?= h(url('author/delete.php')) ?>" class="inline"
                          onsubmit="return confirm('確定刪除這篇稿件？')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                        <button class="btn btn-sm btn-danger">刪除</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-sm" href="<?= h(url('author/view.php?id=' . $m['id'])) ?>">檢視</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
