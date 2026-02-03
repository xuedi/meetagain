<?php declare(strict_types=1);

namespace Plugin\AdminTables\Service;

use App\Entity\AdminLink;
use App\Entity\AdminSection;
use App\Service\AdminNavigationExtensionInterface;

readonly class AdminTablesNavigation implements AdminNavigationExtensionInterface
{
    public function getPriority(): int
    {
        return 800;
    }

    public function getAdminSections(): array
    {
        return [
            new AdminSection(section: 'Tables', links: [
                new AdminLink('menu_admin_event', 'app_admin_event', 'event'),
                new AdminLink('menu_admin_location', 'app_admin_location', 'location'),
                new AdminLink('menu_admin_host', 'app_admin_host', 'host'),
                new AdminLink('menu_admin_image', 'app_admin_image', 'image'),
                new AdminLink('menu_admin_user', 'app_admin_user', 'user'),
            ]),
        ];
    }
}
