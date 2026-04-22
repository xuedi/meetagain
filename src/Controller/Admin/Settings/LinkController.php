<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Entity\AdminLink;

final class LinkController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'admin_shell.section_system',
            links: [
                new AdminLink(
                    label: 'admin_shell.menu_system',
                    route: 'app_admin_system_config',
                    active: 'system',
                    role: 'ROLE_ADMIN',
                ),
            ],
            sectionPriority: 100,
        );
    }
}
