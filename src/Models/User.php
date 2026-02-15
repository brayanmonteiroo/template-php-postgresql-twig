<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class User
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, email, password_hash, status, created_at, updated_at FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, email, password_hash, status, created_at, updated_at FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getPermissionSlugsByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT DISTINCT p.slug
            FROM permissions p
            INNER JOIN role_permission rp ON rp.permission_id = p.id
            INNER JOIN user_role ur ON ur.role_id = rp.role_id
            WHERE ur.user_id = ?
        ');
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'slug');
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, email, status, created_at FROM users ORDER BY name');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (name, email, password_hash, status) VALUES (?, ?, ?, ?) RETURNING id');
        $stmt->execute([
            $data['name'],
            $data['email'],
            $data['password_hash'],
            $data['status'] ?? 'active',
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $row['id'];
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        if (isset($data['name'])) {
            $fields[] = 'name = ?';
            $params[] = $data['name'];
        }
        if (isset($data['email'])) {
            $fields[] = 'email = ?';
            $params[] = $data['email'];
        }
        if (isset($data['password_hash'])) {
            $fields[] = 'password_hash = ?';
            $params[] = $data['password_hash'];
        }
        if (isset($data['status'])) {
            $fields[] = 'status = ?';
            $params[] = $data['status'];
        }
        if (empty($fields)) {
            return true;
        }
        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $params[] = $id;
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function getRolesByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT r.id, r.name, r.slug
            FROM roles r
            INNER JOIN user_role ur ON ur.role_id = r.id
            WHERE ur.user_id = ?
        ');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function syncRoles(int $userId, array $roleIds): void
    {
        $this->pdo->prepare('DELETE FROM user_role WHERE user_id = ?')->execute([$userId]);
        $stmt = $this->pdo->prepare('INSERT INTO user_role (user_id, role_id) VALUES (?, ?)');
        foreach ($roleIds as $roleId) {
            $stmt->execute([$userId, $roleId]);
        }
    }
}
