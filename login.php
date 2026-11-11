<?php
require_once __DIR__ . '/includes/helpers.php';

if ($user = current_user()) {
    redirect(home_path($user));
}

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT id, username, password_hash, display_name, role FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    if ($row && password_verify($password, $row['password_hash'])) {
        session_regenerate_id(true);
        unset($row['password_hash']);
        $_SESSION['user'] = $row;
        flash('歡迎回來，' . $row['display_name'] . '！');
        redirect(home_path($row));
    }
    $error = '帳號或密碼錯誤。';
}

$pageTitle = '登入';
require __DIR__ . '/includes/header.php';
?>
<section class="card login-card">
    <h1>登入</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
    <form method="post" class="form">
        <?= csrf_field() ?>
        <label>帳號
            <input type="text" name="username" value="<?= h($username) ?>" required autofocus>
        </label>
        <label>密碼
            <input type="password" name="password" required>
        </label>
        <button type="submit" class="btn btn-primary btn-block">登入</button>
    </form>
    <div class="demo-accounts">
        <p>測試帳號</p>
        <ul>
            <li>作者：<code>alice</code> / <code>author123</code></li>
            <li>作者：<code>bob</code> / <code>author123</code></li>
            <li>編輯：<code>editor</code> / <code>editor123</code></li>
        </ul>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
