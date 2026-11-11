<?php
require_once __DIR__ . '/../includes/manuscripts.php';

$user = require_role('author');
require_post();
$id = (int) ($_POST['id'] ?? 0);

if (delete_manuscript($id, (int) $user['id'])) {
    flash('稿件已刪除。');
} else {
    flash('只能刪除自己「編輯中」的稿件。', 'error');
}
redirect('author/index.php');
