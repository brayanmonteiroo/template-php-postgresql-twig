<?php

declare(strict_types=1);

function redirect(string $path, int $statusCode = 302): void
{
    $path = '/' . ltrim($path, '/');
    $config = $GLOBALS['app_config'] ?? [];
    $base = rtrim($config['url'] ?? '', '/');
    header('Location: ' . $base . $path, true, $statusCode);
    exit;
}

function validate_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $valid = isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    if (!$valid) {
        http_response_code(403);
        echo '403 Forbidden - invalid CSRF token';
        return false;
    }
    return true;
}
