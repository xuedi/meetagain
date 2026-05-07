<?php declare(strict_types=1);

namespace App\Controller\Admin\Security;

use App\Admin\Navigation\AdminLink;
use App\Admin\Navigation\AdminNavigationConfig;
use App\Admin\Tabs\AdminTab;
use App\Admin\Tabs\AdminTabs;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractSecurityController extends AbstractController
{
    public function __construct(
        protected readonly TranslatorInterface $translator,
        private readonly string $activeSecurityTab,
    ) {}

    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'admin_shell.section_system',
            links: [
                new AdminLink(
                    label: 'admin_shell.menu_security',
                    route: 'app_admin_security_url_probing',
                    active: 'security',
                    role: 'ROLE_ADMIN',
                ),
            ],
            sectionPriority: 100,
        );
    }

    final public function getTabs(): AdminTabs
    {
        return new AdminTabs([
            new AdminTab(
                label: $this->translator->trans('admin_security.tab_url_probing'),
                target: $this->generateUrl('app_admin_security_url_probing'),
                icon: 'radar',
                isActive: $this->activeSecurityTab === 'url_probing',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_security.tab_access_denied'),
                target: $this->generateUrl('app_admin_security_access_denied'),
                icon: 'ban',
                isActive: $this->activeSecurityTab === 'access_denied',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_security.tab_brute_force'),
                target: $this->generateUrl('app_admin_security_brute_force'),
                icon: 'shield',
                isActive: $this->activeSecurityTab === 'brute_force',
            ),
        ]);
    }
}
