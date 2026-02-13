<?php declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface AdminNavigationInterface
{
    public function getAdminNavigation(): ?AdminNavigationConfig;
}
