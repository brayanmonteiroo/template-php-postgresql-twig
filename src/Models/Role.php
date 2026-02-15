<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Role
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, slug FROM roles ORDER BY name');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, slug FROM roles WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, slug FROM roles WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO roles (name, slug) VALUES (?, ?) RETURNING id');
        $stmt->execute([$data['name'], $data['slug']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $row['id'];
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare('UPDATE roles SET name = ?, slug = ? WHERE id = ?');
        return $stmt->execute([$data['name'], $data['slug'], $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM roles WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function getPermissionIdsByRoleId(int $roleId): array
    {
        $stmt = $this->pdo->prepare('SELECT permission_id FROM role_permission WHERE role_id = ?');
        $stmt->execute([$roleId]);
        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'permission_id'));
    }

    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        $this->pdo->prepare('DELETE FROM role_permission WHERE role_id = ?')->execute([$roleId]);
        $stmt = $this->pdo->prepare('INSERT INTO role_permission (role_id, permission_id) VALUES (?, ?)');
        foreach ($permissionIds as $pid) {
            $stmt->execute([$roleId, (int) $pid]);
        }
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM roles WHERE slug = ? AND id != ? LIMIT 1');
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT 1 FROM roles WHERE slug = ? LIMIT 1');
            $stmt->execute([$slug]);
        }
        return (bool) $stmt->fetchColumn();
    }
}
