<?php declare(strict_types=1);

namespace App\AdminModules\System;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use Symfony\Bundle\SecurityBundle\Security;

readonly class SystemModule implements AdminModuleInterface
{
    public function __construct(
        private Security $security,
    ) {}

    public function getKey(): string
    {
        return 'system';
    }

    public function getPriority(): int
    {
        return 1000; // System should be first
    }

    public function getSectionName(): string
    {
        return 'System';
    }

    public function getLinks(): array
    {
        return [
            new AdminLink(label: 'menu_admin_system', route: 'app_admin_system', active: 'system'),
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'name' => 'app_admin_system',
                'path' => '/admin/system',
                'controller' => [SystemController::class, 'index'],
                'methods' => ['GET', 'POST'],
            ],
            [
                'name' => 'app_admin_regenerate_thumbnails',
                'path' => '/admin/system/regenerate_thumbnails',
                'controller' => [SystemController::class, 'regenerateThumbnails'],
                'methods' => ['POST'],
            ],
            [
                'name' => 'app_admin_cleanup_thumbnails',
                'path' => '/admin/system/cleanup_thumbnails',
                'controller' => [SystemController::class, 'cleanupThumbnails'],
                'methods' => ['POST'],
            ],
            [
                'name' => 'app_admin_system_boolean',
                'path' => '/admin/system/boolean/{name}',
                'controller' => [SystemController::class, 'boolean'],
                'methods' => ['POST'],
            ],
        ];
    }

    public function isAccessible(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }
}
