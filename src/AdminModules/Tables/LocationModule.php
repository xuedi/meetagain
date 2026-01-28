<?php declare(strict_types=1);

namespace App\AdminModules\Tables;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use Symfony\Bundle\SecurityBundle\Security;

readonly class LocationModule implements AdminModuleInterface
{
    public function __construct(
        private Security $security,
    ) {}

    public function getKey(): string
    {
        return 'location';
    }

    public function getPriority(): int
    {
        return 790; // After Event in Tables section
    }

    public function getSectionName(): string
    {
        return 'Tables';
    }

    public function getLinks(): array
    {
        return [
            new AdminLink(label: 'menu_admin_location', route: 'app_admin_location', active: 'location'),
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'name' => 'app_admin_location',
                'path' => '/admin/location/',
                'controller' => [LocationController::class, 'locationList'],
            ],
            [
                'name' => 'app_admin_location_add',
                'path' => '/admin/location/add',
                'controller' => [LocationController::class, 'locationAdd'],
                'methods' => ['GET', 'POST'],
            ],
            [
                'name' => 'app_admin_location_edit',
                'path' => '/admin/location/edit/{id}',
                'controller' => [LocationController::class, 'locationEdit'],
                'methods' => ['GET', 'POST'],
            ],
        ];
    }

    public function isAccessible(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }
}
