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

function security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
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

function rate_limit_login_ok(): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '') {
        return true;
    }
    $basePath = $GLOBALS['app_base_path'] ?? dirname(__DIR__, 2);
    $file = $basePath . '/storage/cache/login_attempts.json';
    $data = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
    }
    $cutoff = time() - (RATE_LIMIT_LOGIN_WINDOW_MINUTES * 60);
    $attempts = $data[$ip] ?? [];
    $attempts = array_filter($attempts, fn ($ts) => $ts > $cutoff);
    return count($attempts) < RATE_LIMIT_LOGIN_MAX_ATTEMPTS;
}

function rate_limit_login_retry_after_minutes(): int
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $basePath = $GLOBALS['app_base_path'] ?? dirname(__DIR__, 2);
    $file = $basePath . '/storage/cache/login_attempts.json';
    $data = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
    }
    $cutoff = time() - (RATE_LIMIT_LOGIN_WINDOW_MINUTES * 60);
    $attempts = $data[$ip] ?? [];
    $attempts = array_values(array_filter($attempts, fn ($ts) => $ts > $cutoff));
    if (count($attempts) < RATE_LIMIT_LOGIN_MAX_ATTEMPTS) {
        return 0;
    }
    $oldest = min($attempts);
    $retryAt = $oldest + (RATE_LIMIT_LOGIN_WINDOW_MINUTES * 60);
    return (int) max(0, ceil(($retryAt - time()) / 60));
}

function rate_limit_login_record(): void
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
    $file = $dir . '/login_attempts.json';
    $data = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
    }
    $cutoff = time() - (RATE_LIMIT_LOGIN_WINDOW_MINUTES * 60);
    $attempts = $data[$ip] ?? [];
    $attempts = array_values(array_filter($attempts, fn ($ts) => $ts > $cutoff));
    $attempts[] = time();
    $data[$ip] = $attempts;
    @file_put_contents($file, json_encode($data), LOCK_EX);
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
