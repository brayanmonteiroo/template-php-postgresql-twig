<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Permission
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, slug FROM permissions ORDER BY name');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
