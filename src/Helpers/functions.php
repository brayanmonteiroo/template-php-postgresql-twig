<?php

declare(strict_types=1);

use Twig\Environment;

function input_get(string $key, mixed $default = ''): mixed
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
}

function input_post(string $key, mixed $default = ''): mixed
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
}

function request_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function security_headers(?string $cspNonce = null): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    if ($cspNonce !== null && $cspNonce !== '') {
        $nonce = preg_replace('/[^a-zA-Z0-9+\/=_-]/', '', $cspNonce) ?? '';
        if ($nonce !== '') {
            header(
                "Content-Security-Policy: default-src 'self'; "
                . "script-src 'self' 'nonce-{$nonce}'; "
                . "style-src 'self' 'unsafe-inline'; "
                . "img-src 'self' data:; "
                . "base-uri 'self'; "
                . "form-action 'self'; "
                . "frame-ancestors 'self'"
            );
        }
    }

    $config = $GLOBALS['app_config'] ?? [];
    if (request_is_https() && ($config['env'] ?? '') === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function rotate_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function redirect(string $path, int $statusCode = 302): void
{
    $path = '/' . ltrim($path, '/');
    $config = $GLOBALS['app_config'] ?? [];
    $base = rtrim($config['url'] ?? '', '/');
    header('Location: ' . $base . $path, true, $statusCode);
    exit;
}

function render_error(Environment $twig, int $code, string $message = ''): void
{
    http_response_code($code);
    $template = "errors/{$code}.twig";
    try {
        echo $twig->render($template, ['message' => $message]);
        return;
    } catch (\Throwable $e) {
        // fallback to plain text if template missing or Twig fails
    }
    $defaults = [
        403 => '403 Forbidden',
        404 => '404 Not Found',
        405 => '405 Method Not Allowed',
        500 => '500 Internal Server Error',
    ];
    echo $defaults[$code] ?? (string) $code;
}

function validate_csrf(?Environment $twig = null): bool
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $valid = isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    if (!$valid) {
        app_log('warning', 'CSRF token invalid', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
        if ($twig !== null) {
            render_error($twig, 403, 'Token CSRF inválido.');
        } else {
            http_response_code(403);
            echo '403 Forbidden - invalid CSRF token';
        }
        return false;
    }
    return true;
}

function flash_set(string $type, string $message): void
{
    if (!isset($_SESSION['_flash'][$type]) || !is_array($_SESSION['_flash'][$type])) {
        $_SESSION['_flash'][$type] = [];
    }
    $_SESSION['_flash'][$type][] = $message;
}

function flash_get(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    foreach ($messages as $type => $list) {
        if (!is_array($list)) {
            $messages[$type] = [$list];
        }
    }
    return $messages;
}

const RATE_LIMIT_LOGIN_MAX_ATTEMPTS = 5;
const RATE_LIMIT_LOGIN_WINDOW_MINUTES = 15;
const RATE_LIMIT_REGISTER_MAX_ATTEMPTS = 5;
const RATE_LIMIT_REGISTER_WINDOW_MINUTES = 15;

/**
 * @return array<string, list<int>>
 */
function rate_limit_load(string $filename): array
{
    $basePath = $GLOBALS['app_base_path'] ?? dirname(__DIR__, 2);
    $file = $basePath . '/storage/cache/' . $filename;
    if (!is_file($file)) {
        return [];
    }
    $raw = @file_get_contents($file);
    if ($raw === false) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function rate_limit_ok(string $filename, int $maxAttempts, int $windowMinutes): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '') {
        return true;
    }
    $data = rate_limit_load($filename);
    $cutoff = time() - ($windowMinutes * 60);
    $attempts = array_filter($data[$ip] ?? [], fn ($ts) => $ts > $cutoff);
    return count($attempts) < $maxAttempts;
}

function rate_limit_retry_after_minutes(string $filename, int $maxAttempts, int $windowMinutes): int
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $data = rate_limit_load($filename);
    $cutoff = time() - ($windowMinutes * 60);
    $attempts = array_values(array_filter($data[$ip] ?? [], fn ($ts) => $ts > $cutoff));
    if (count($attempts) < $maxAttempts) {
        return 0;
    }
    $oldest = min($attempts);
    $retryAt = $oldest + ($windowMinutes * 60);
    return (int) max(0, ceil(($retryAt - time()) / 60));
}

function rate_limit_record(string $filename, int $windowMinutes): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '') {
        return;
    }
    $basePath = $GLOBALS['app_base_path'] ?? dirname(__DIR__, 2);
    $dir = $basePath . '/storage/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . '/' . $filename;
    $data = rate_limit_load($filename);
    $cutoff = time() - ($windowMinutes * 60);
    $attempts = array_values(array_filter($data[$ip] ?? [], fn ($ts) => $ts > $cutoff));
    $attempts[] = time();
    $data[$ip] = $attempts;
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

function rate_limit_login_ok(): bool
{
    return rate_limit_ok(
        'login_attempts.json',
        RATE_LIMIT_LOGIN_MAX_ATTEMPTS,
        RATE_LIMIT_LOGIN_WINDOW_MINUTES
    );
}

function rate_limit_login_retry_after_minutes(): int
{
    return rate_limit_retry_after_minutes(
        'login_attempts.json',
        RATE_LIMIT_LOGIN_MAX_ATTEMPTS,
        RATE_LIMIT_LOGIN_WINDOW_MINUTES
    );
}

function rate_limit_login_record(): void
{
    rate_limit_record('login_attempts.json', RATE_LIMIT_LOGIN_WINDOW_MINUTES);
}

function rate_limit_register_ok(): bool
{
    return rate_limit_ok(
        'register_attempts.json',
        RATE_LIMIT_REGISTER_MAX_ATTEMPTS,
        RATE_LIMIT_REGISTER_WINDOW_MINUTES
    );
}

function rate_limit_register_retry_after_minutes(): int
{
    return rate_limit_retry_after_minutes(
        'register_attempts.json',
        RATE_LIMIT_REGISTER_MAX_ATTEMPTS,
        RATE_LIMIT_REGISTER_WINDOW_MINUTES
    );
}

function rate_limit_register_record(): void
{
    rate_limit_record('register_attempts.json', RATE_LIMIT_REGISTER_WINDOW_MINUTES);
}

function app_log(string $level, string $message, array $context = []): void
{
    $basePath = $GLOBALS['app_base_path'] ?? dirname(__DIR__, 2);
    $logDir = $basePath . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/app.log';
    $contextStr = empty($context) ? '' : ' ' . json_encode($context);
    $line = sprintf(
        "[%s] %s: %s%s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message,
        $contextStr
    );
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
