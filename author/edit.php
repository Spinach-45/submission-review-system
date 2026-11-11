<?php
require_once __DIR__ . '/../includes/manuscripts.php';

$user = require_role('author');
$authorId = (int) $user['id'];
$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;

$manuscript = ['category_id' => '', 'title' => '', 'content' => ''];
if ($isEdit) {
    $manuscript = find_editable($id, $authorId);
    if (!$manuscript) {
        flash('找不到稿件，或稿件已送出審查無法編輯。', 'error');
        redirect('author/index.php');
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $input = [
        'category_id' => $_POST['category_id'] ?? '',
        'title'       => $_POST['title'] ?? '',
        'content'     => $_POST['content'] ?? '',
    ];
    $errors = validate_manuscript($input);
    if (!$errors) {
        if ($isEdit) {
            if (update_manuscript($id, $authorId, $input)) {
                flash('稿件已儲存。');
            } else {
                flash('稿件已送出審查，無法再編輯。', 'error');
            }
        } else {
            $id = create_manuscript($authorId, $input);
            flash('已新增稿件（狀態：編輯中）。');
        }
        redirect('author/view.php?id=' . $id);
    }
    $manuscript = array_merge($manuscript, $input);
}

$rejected = $isEdit ? latest_reject_comment($id) : null;
$pageTitle = $isEdit ? '編輯投稿' : '新增投稿';
require __DIR__ . '/../includes/header.php';
?>
<h1><?= h($pageTitle) ?></h1>

<?php if ($rejected && $rejected['comment']): ?>
    <div class="alert alert-warning"><strong>編輯退回意見：</strong><?= nl2br(h($rejected['comment'])) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="form card">
    <?= csrf_field() ?>
    <label>類別
        <select name="category_id" required>
            <option value="">請選擇</option>
            <?php foreach (categories() as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int) $manuscript['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>標題
        <input type="text" name="title" maxlength="200" value="<?= h($manuscript['title']) ?>" required>
    </label>
    <label>文章內容
        <textarea name="content" rows="14" required><?= h($manuscript['content']) ?></textarea>
    </label>
    <div class="form-actions">
        <a class="btn" href="<?= h(url('author/index.php')) ?>">取消</a>
        <button type="submit" class="btn btn-primary">儲存</button>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
