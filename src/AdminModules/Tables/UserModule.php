<?php declare(strict_types=1);

namespace App\AdminModules\Tables;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use Symfony\Bundle\SecurityBundle\Security;

readonly class UserModule implements AdminModuleInterface
{
    public function __construct(
        private Security $security,
    ) {}

    public function getKey(): string
    {
        return 'user';
    }

    public function getPriority(): int
    {
        return 760; // After Image in Tables section
    }

    public function getSectionName(): string
    {
        return 'Tables';
    }

    public function getLinks(): array
    {
        return [
            new AdminLink(label: 'menu_admin_user', route: 'app_admin_user', active: 'user'),
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'name' => 'app_admin_user',
                'path' => '/admin/user',
                'controller' => [UserController::class, 'userList'],
            ],
            [
                'name' => 'app_admin_user_edit',
                'path' => '/admin/user/{id}',
                'controller' => [UserController::class, 'userEdit'],
                'methods' => ['GET', 'POST'],
            ],
            [
                'name' => 'app_admin_user_approve',
                'path' => '/admin/user/{id}/approve',
                'controller' => [UserController::class, 'userApprove'],
                'methods' => ['GET'],
            ],
            [
                'name' => 'app_admin_user_deny',
                'path' => '/admin/user/{id}/deny',
                'controller' => [UserController::class, 'userDeny'],
                'methods' => ['GET'],
            ],
            [
                'name' => 'app_admin_user_delete',
                'path' => '/admin/user/{id}/delete',
                'controller' => [UserController::class, 'userDelete'],
                'methods' => ['GET'],
            ],
        ];
    }

    public function isAccessible(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }
}
