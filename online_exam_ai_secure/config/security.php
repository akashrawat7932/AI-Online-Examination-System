<?php
function e(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void
{
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf_token'])) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}
function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/empoly_login.php');
        exit;
    }
}

function require_role(string ...$roles): void
{
    require_login();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        http_response_code(403);
        exit('Forbidden.');
    }
}
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(self), microphone=(self)');
