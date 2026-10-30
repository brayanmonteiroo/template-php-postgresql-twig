<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DashboardService;

class DashboardController
{
    public function __construct(
        private array $container
    ) {
    }

    private function dashboardService(): DashboardService
    {
        return $this->container['dashboardService'];
    }

    public function index(): void
    {
        $twig = $this->container['twig'];
        echo $twig->render('dashboard/index.twig', $this->dashboardService()->getTotals());
    }
}
