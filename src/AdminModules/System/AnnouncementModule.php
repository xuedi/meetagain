<?php declare(strict_types=1);

namespace App\AdminModules\System;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use Symfony\Bundle\SecurityBundle\Security;

readonly class AnnouncementModule implements AdminModuleInterface
{
    public function __construct(
        private Security $security,
    ) {}

    public function getKey(): string
    {
        return 'announcement';
    }

    public function getPriority(): int
    {
        return 960; // Last in System section
    }

    public function getSectionName(): string
    {
        return 'System';
    }

    public function getLinks(): array
    {
        return [
            new AdminLink(label: 'menu_admin_announcement', route: 'app_admin_announcement', active: 'announcement'),
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'name' => 'app_admin_announcement',
                'path' => '/admin/system/announcements',
                'controller' => [AnnouncementController::class, 'list'],
            ],
            [
                'name' => 'app_admin_announcement_new',
                'path' => '/admin/system/announcements/new',
                'controller' => [AnnouncementController::class, 'new'],
                'methods' => ['GET', 'POST'],
            ],
            [
                'name' => 'app_admin_announcement_from_cms',
                'path' => '/admin/system/announcements/from-cms/{id}',
                'controller' => [AnnouncementController::class, 'createFromCms'],
            ],
            [
                'name' => 'app_admin_announcement_view',
                'path' => '/admin/system/announcements/{id}',
                'controller' => [AnnouncementController::class, 'view'],
            ],
            [
                'name' => 'app_admin_announcement_send',
                'path' => '/admin/system/announcements/{id}/send',
                'controller' => [AnnouncementController::class, 'send'],
                'methods' => ['POST'],
            ],
            [
                'name' => 'app_admin_announcement_delete',
                'path' => '/admin/system/announcements/{id}/delete',
                'controller' => [AnnouncementController::class, 'delete'],
                'methods' => ['POST'],
            ],
        ];
    }

    public function isAccessible(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }
}
