<?php declare(strict_types=1);

namespace App\Controller\Admin\Translation;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Entity\AdminLink;

class LinkController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(section: 'System', links: [
            new AdminLink(
                label: 'menu_admin_translation',
                route: 'app_admin_translation',
                active: 'translation',
                role: 'ROLE_ADMIN',
            ),
        ]);
    }
}
