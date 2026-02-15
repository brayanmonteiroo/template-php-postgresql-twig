<?php

return [
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => (bool) ($_ENV['APP_DEBUG'] ?? false),
    'url' => rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/'),
    'session_lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 7200),
];
