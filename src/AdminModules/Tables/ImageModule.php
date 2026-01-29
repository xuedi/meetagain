<?php declare(strict_types=1);

namespace App\AdminModules\Tables;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use Symfony\Bundle\SecurityBundle\Security;

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
        return 770; // After Event, Location, and Host in Tables section
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
        ];
    }

    public function isAccessible(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }
}
