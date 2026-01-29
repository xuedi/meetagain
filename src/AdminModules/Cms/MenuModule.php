<?php declare(strict_types=1);

namespace App\AdminModules\Cms;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use App\Entity\User;
use App\Entity\UserRole;
use App\Security\Attribute\RequiresRole;
use Symfony\Bundle\SecurityBundle\Security;

#[RequiresRole(UserRole::Admin)]
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
                'path' => '/admin/menu',
                'controller' => [MenuController::class, 'menuList'],
            ],
            [
                'name' => 'app_admin_menu_add',
                'path' => '/admin/menu/add',
                'controller' => [MenuController::class, 'menuAdd'],
                'methods' => ['POST'],
            ],
            [
                'name' => 'app_admin_menu_edit',
                'path' => '/admin/menu/edit/{id}',
                'controller' => [MenuController::class, 'menuEdit'],
                'methods' => ['POST'],
            ],
            [
                'name' => 'app_admin_menu_delete',
                'path' => '/admin/menu/delete',
                'controller' => [MenuController::class, 'menuDelete'],
                'methods' => ['GET'],
            ],
            [
                'name' => 'app_admin_menu_up',
                'path' => '/admin/menu/up',
                'controller' => [MenuController::class, 'menuUp'],
                'methods' => ['GET'],
            ],
            [
                'name' => 'app_admin_menu_down',
                'path' => '/admin/menu/down',
                'controller' => [MenuController::class, 'menuDown'],
                'methods' => ['GET'],
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
