<?php

$basePath = __DIR__;

require $basePath . '/vendor/autoload.php';
require $basePath . '/config/env.php';

$dotenv = Dotenv\Dotenv::createImmutable($basePath);
$dotenv->safeLoad();

$passwordFile = env_value('DB_PASSWORD_FILE', '');
if ($passwordFile !== null && $passwordFile !== '' && is_readable($passwordFile)) {
    $dbPassword = trim((string) file_get_contents($passwordFile));
} else {
    $dbPassword = env_value('DB_PASSWORD', '') ?? '';
}

return [
    'paths' => [
        'migrations' => $basePath . '/database/migrations',
        'seeds' => $basePath . '/database/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'pgsql',
            'host' => env_value('DB_HOST', 'localhost'),
            'port' => env_value('DB_PORT', '5432'),
            'name' => env_value('DB_NAME', 'template_admin'),
            'user' => env_value('DB_USER', 'postgres'),
            'pass' => $dbPassword,
            'charset' => 'utf8',
        ],
        'production' => [
            'adapter' => 'pgsql',
            'host' => env_value('DB_HOST', 'localhost'),
            'port' => env_value('DB_PORT', '5432'),
            'name' => env_value('DB_NAME', 'template_admin'),
            'user' => env_value('DB_USER', 'postgres'),
            'pass' => $dbPassword,
            'charset' => 'utf8',
        ],
    ],
];
