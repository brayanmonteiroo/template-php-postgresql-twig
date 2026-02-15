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
}
