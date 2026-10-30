<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

$host = env_value('DB_HOST', 'localhost');
$port = env_value('DB_PORT', '5432');
$dbname = env_value('DB_NAME', 'template_admin');
$user = env_value('DB_USER', 'postgres');

$passwordFile = env_value('DB_PASSWORD_FILE', '');
if ($passwordFile !== null && $passwordFile !== '' && is_readable($passwordFile)) {
    $password = trim((string) file_get_contents($passwordFile));
} else {
    $password = env_value('DB_PASSWORD', '') ?? '';
}

$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    $host,
    $port,
    $dbname
);

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

return new PDO($dsn, $user, $password, $options);
