<?php declare(strict_types=1);

namespace App\AdminModules\Tables;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use App\Entity\User;
use App\Entity\UserRole;
use App\Security\Attribute\RequiresRole;
use Symfony\Bundle\SecurityBundle\Security;

#[RequiresRole(UserRole::Admin)]
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
                'path' => '/admin/location',
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
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }
        return $user->hasUserRole(UserRole::Admin);
    }
}
