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
        $twig = $this->container['twig'];
        echo $twig->render('dashboard/index.twig');
    }
}
