<?php

$basePath = __DIR__;

require $basePath . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($basePath);
$dotenv->safeLoad();

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
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? '5432',
            'name' => $_ENV['DB_NAME'] ?? 'template_admin',
            'user' => $_ENV['DB_USER'] ?? 'postgres',
            'pass' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8',
        ],
        'production' => [
            'adapter' => 'pgsql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? '5432',
            'name' => $_ENV['DB_NAME'] ?? 'template_admin',
            'user' => $_ENV['DB_USER'] ?? 'postgres',
            'pass' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8',
        ],
    ],
];
