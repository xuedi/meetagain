<?php declare(strict_types=1);

namespace App\Controller\Admin\Support;

use App\Admin\Navigation\AdminLink;
use App\Admin\Navigation\AdminNavigationConfig;
use App\Admin\Tabs\AdminTab;
use App\Admin\Tabs\AdminTabs;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractSupportController extends AbstractController
{
    public function __construct(
        protected readonly TranslatorInterface $translator,
        private readonly string $activeSupportTab,
    ) {}

    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'admin_shell.section_system',
            links: [
                new AdminLink(
                    label: 'admin_shell.menu_support',
                    route: 'app_admin_support_list',
                    active: 'support',
                    role: 'ROLE_ADMIN',
                ),
            ],
        );
    }

    final public function getTabs(): AdminTabs
    {
        return new AdminTabs([
            new AdminTab(
                label: $this->translator->trans('admin_support.tab_requests'),
                target: $this->generateUrl('app_admin_support_list'),
                icon: 'life-ring',
                isActive: $this->activeSupportTab === 'requests',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_support.tab_reports'),
                target: $this->generateUrl('app_admin_support_reports'),
                icon: 'flag',
                isActive: $this->activeSupportTab === 'reports',
            ),
        ]);
    }
}
