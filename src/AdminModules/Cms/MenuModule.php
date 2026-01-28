<?php declare(strict_types=1);

namespace App\AdminModules\Cms;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use Symfony\Bundle\SecurityBundle\Security;

readonly class MenuModule implements AdminModuleInterface
{
    public function __construct(
        private Security $security,
    ) {}

    public function getKey(): string
    {
        return 'menu';
    }

    public function getPriority(): int
    {
        return 590; // After CMS in CMS section
    }

    public function getSectionName(): string
    {
        return 'CMS';
    }

    public function getLinks(): array
    {
        return [
            new AdminLink(label: 'menu_admin_menu', route: 'app_admin_menu', active: 'menu'),
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'name' => 'app_admin_menu',
                'path' => '/admin/menu/{edit}',
                'controller' => [MenuController::class, 'menuList'],
                'defaults' => ['edit' => null],
            ],
            [
                'name' => 'app_admin_menu_up',
                'path' => '/admin/menu/{id}/up',
                'controller' => [MenuController::class, 'menuUp'],
            ],
            [
                'name' => 'app_admin_menu_down',
                'path' => '/admin/menu/{id}/down',
                'controller' => [MenuController::class, 'menuDown'],
            ],
            [
                'name' => 'app_admin_menu_delete',
                'path' => '/admin/menu/{id}/delete',
                'controller' => [MenuController::class, 'menuDelete'],
                'methods' => ['GET'],
            ],
        ];
    }

    public function isAccessible(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }
}
