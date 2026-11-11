<?php
/** @var string $pageTitle */
$user = current_user();
$flash = take_flash();
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle ?? '') ?> | 投稿審查系統</title>
    <link rel="stylesheet" href="<?= h(url('assets/style.css')) ?>">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <a class="brand" href="<?= h(url($user ? home_path($user) : 'login.php')) ?>">投稿審查系統</a>
        <?php if ($user): ?>
            <nav class="nav">
                <?php if ($user['role'] === 'author'): ?>
                    <a href="<?= h(url('author/index.php')) ?>">我的投稿</a>
                    <a href="<?= h(url('author/edit.php')) ?>">新增投稿</a>
                <?php else: ?>
                    <a href="<?= h(url('editor/index.php')) ?>">待審查清單</a>
                <?php endif; ?>
                <span class="user-chip"><?= h($user['display_name']) ?>（<?= $user['role'] === 'editor' ? '編輯' : '作者' ?>）</span>
                <form method="post" action="<?= h(url('logout.php')) ?>" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-link">登出</button>
                </form>
            </nav>
        <?php endif; ?>
    </div>
</header>
<main class="container">
<?php if ($flash): ?>
    <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
<?php endif; ?>
