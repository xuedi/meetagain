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
                    route: 'app_admin_security_incidents',
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
                label: $this->translator->trans('admin_security.tab_incidents'),
                target: $this->generateUrl('app_admin_security_incidents'),
                icon: 'radar',
                isActive: $this->activeSecurityTab === 'incidents',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_security.tab_blocked_sessions'),
                target: $this->generateUrl('app_admin_security_blocked'),
                icon: 'ban',
                isActive: $this->activeSecurityTab === 'blocked',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_security.tab_rate_limiting'),
                target: $this->generateUrl('app_admin_security_rate_limiting'),
                icon: 'tachometer-alt',
                isActive: $this->activeSecurityTab === 'rate_limiting',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_security.tab_permissions'),
                target: $this->generateUrl('app_admin_security_permissions'),
                icon: 'shield-alt',
                isActive: $this->activeSecurityTab === 'permissions',
            ),
        ]);
    }
}
