<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

return [
    'env' => env_value('APP_ENV', 'production'),
    'debug' => (bool) (env_value('APP_DEBUG', '0') ?? '0'),
    'url' => rtrim(env_value('APP_URL', 'http://localhost') ?? 'http://localhost', '/'),
    'session_lifetime' => (int) (env_value('SESSION_LIFETIME', '7200') ?? '7200'),
];
