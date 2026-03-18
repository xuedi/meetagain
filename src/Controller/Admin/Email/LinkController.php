<?php declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Entity\AdminLink;

final class LinkController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'System',
            links: [
                new AdminLink(
                    label: 'menu_admin_email',
                    route: 'app_admin_email_templates',
                    active: 'email',
                    role: 'ROLE_ADMIN',
                ),
            ],
            sectionPriority: 100,
        );
    }
}
