<?php
require_once __DIR__ . '/../includes/manuscripts.php';

$user = require_role('author');
require_post();
$id = (int) ($_POST['id'] ?? 0);

if (submit_manuscript($id, (int) $user['id'])) {
    flash('已送出投稿，狀態改為「審查中」。');
} else {
    flash('只能送出自己「編輯中」的稿件。', 'error');
}
redirect('author/index.php');
