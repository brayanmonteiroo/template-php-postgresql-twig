<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($basePath);
$dotenv->safeLoad();

$pdo = require $basePath . '/config/database.php';

$email = 'admin@example.com';
$password = 'admin123';
$name = 'Administrador';
$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, status) VALUES (?, ?, ?, ?) ON CONFLICT (email) DO NOTHING');
$stmt->execute([$name, $email, $hash, 'active']);

$stmt = $pdo->prepare('INSERT INTO user_role (user_id, role_id) SELECT u.id, r.id FROM users u, roles r WHERE u.email = ? AND r.slug = ? ON CONFLICT DO NOTHING');
$stmt->execute([$email, 'admin']);

echo "Admin user created: {$email} / {$password}\n";
