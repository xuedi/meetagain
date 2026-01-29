<?php declare(strict_types=1);

namespace App\AdminModules\Tables;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use App\Entity\User;
use App\Entity\UserRole;
use App\Security\Attribute\RequiresRole;
use Symfony\Bundle\SecurityBundle\Security;

#[RequiresRole(UserRole::Admin)]
readonly class ImageModule implements AdminModuleInterface
{
    public function __construct(
        private Security $security,
    ) {}

    public function getKey(): string
    {
        return 'image';
    }

    public function getPriority(): int
    {
        return 770; // After Host in Tables section
    }

    public function getSectionName(): string
    {
        return 'Tables';
    }

    public function getLinks(): array
    {
        return [
            new AdminLink(label: 'menu_admin_image', route: 'app_admin_image', active: 'image'),
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'name' => 'app_admin_image',
                'path' => '/admin/image',
                'controller' => [ImageController::class, 'imageList'],
            ],
            [
                'name' => 'app_admin_image_add',
                'path' => '/admin/image/add',
                'controller' => [ImageController::class, 'imageAdd'],
                'methods' => ['GET', 'POST'],
            ],
            [
                'name' => 'app_admin_image_edit',
                'path' => '/admin/image/edit/{id}',
                'controller' => [ImageController::class, 'imageEdit'],
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
