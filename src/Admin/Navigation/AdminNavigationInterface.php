<?php declare(strict_types=1);

namespace App\Admin\Navigation;

interface AdminNavigationInterface
{
    public function getAdminNavigation(): ?AdminNavigationConfig;
}
