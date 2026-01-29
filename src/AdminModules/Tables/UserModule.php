<?php declare(strict_types=1);

namespace App\AdminModules\Tables;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use App\Entity\User;
use App\Entity\UserRole;
use App\Security\Attribute\RequiresRole;
use Symfony\Bundle\SecurityBundle\Security;

#[RequiresRole(UserRole::Admin)]
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
                'name' => 'app_admin_user_add',
                'path' => '/admin/user/add',
                'controller' => [UserController::class, 'userAdd'],
                'methods' => ['GET', 'POST'],
            ],
            [
                'name' => 'app_admin_user_edit',
                'path' => '/admin/user/edit/{id}',
                'controller' => [UserController::class, 'userEdit'],
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
