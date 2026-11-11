<?php
// 讀取專案根目錄的 .env（若存在），否則使用環境變數或預設值
$envFile = dirname(__DIR__) . '/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (getenv($key) === false) {
            putenv("$key=$value");
        }
    }
}

function env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

// 網站根路徑：自動偵測專案放在網站根目錄或子資料夾（例如 XAMPP 的 htdocs/submission-review-system）
function detect_base_url(): string
{
    if (getenv('BASE_URL') !== false) {
        return rtrim(getenv('BASE_URL'), '/');
    }
    $root = realpath(dirname(__DIR__));
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($root && $docRoot && str_starts_with($root, $docRoot)) {
        return rtrim(str_replace('\\', '/', substr($root, strlen($docRoot))), '/');
    }
    return '';
}

define('BASE_URL', detect_base_url());
