<?php

declare(strict_types=1);

namespace App\Controllers;

class DashboardController
{
    public function __construct(
        private array $container
    ) {
    }

    public function index(): void
    {
        $userModel = $this->container['userModel'];
        $roleModel = $this->container['roleModel'];
        $permissionModel = $this->container['permissionModel'];
        $twig = $this->container['twig'];
        echo $twig->render('dashboard/index.twig', [
            'total_users' => $userModel->countAll('', ''),
            'total_roles' => count($roleModel->listAll()),
            'total_permissions' => count($permissionModel->listAll()),
        ]);
    }
}
