<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const STATUS_LABELS = [
    'editing'   => '編輯中',
    'reviewing' => '審查中',
    'approved'  => '已通過',
];

const ACTION_LABELS = [
    'submit'  => '送出投稿',
    'approve' => '審查通過',
    'reject'  => '退回修改',
];

/** HTML 跳脫，防止 XSS */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function status_badge(string $status): string
{
    return sprintf('<span class="badge badge-%s">%s</span>', h($status), h(STATUS_LABELS[$status] ?? $status));
}

/* ---------- 提示訊息 ---------- */

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/* ---------- 登入與權限 ---------- */

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** 要求登入；指定角色時，角色不符直接回 403 */
function require_role(?string $role = null): array
{
    $user = current_user();
    if ($user === null) {
        flash('請先登入。', 'error');
        redirect('login.php');
    }
    if ($role !== null && $user['role'] !== $role) {
        http_response_code(403);
        exit('403 Forbidden：你沒有權限存取此頁面。');
    }
    return $user;
}

function home_path(array $user): string
{
    return $user['role'] === 'editor' ? 'editor/index.php' : 'author/index.php';
}

/* ---------- CSRF 防護 ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

/** 只接受 POST 並驗證 CSRF token */
function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('405 Method Not Allowed');
    }
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('400 Bad Request：表單已失效，請重新整理後再試。');
    }
}
