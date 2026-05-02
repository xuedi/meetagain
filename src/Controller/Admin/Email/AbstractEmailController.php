<?php declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Admin\Navigation\AdminLink;
use App\Admin\Navigation\AdminNavigationConfig;
use App\Admin\Tabs\AdminTab;
use App\Admin\Tabs\AdminTabs;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractEmailController extends AbstractController
{
    public function __construct(
        protected readonly TranslatorInterface $translator,
        private readonly string $activeEmailTab,
    ) {}

    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'admin_shell.section_system',
            links: [
                new AdminLink(
                    label: 'admin_shell.menu_email',
                    route: 'app_admin_email_templates',
                    active: 'email',
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
                label: $this->translator->trans('admin_email.tab_templates'),
                target: $this->generateUrl('app_admin_email_templates'),
                icon: 'envelope',
                isActive: $this->activeEmailTab === 'templates',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_email.tab_sendlog'),
                target: $this->generateUrl('app_admin_email_sendlog'),
                icon: 'list',
                isActive: $this->activeEmailTab === 'sendlog',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_email.tab_announcements'),
                target: $this->generateUrl('app_admin_email_announcements'),
                icon: 'bullhorn',
                isActive: $this->activeEmailTab === 'announcements',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_email.tab_planned'),
                target: $this->generateUrl('app_admin_email_planned'),
                icon: 'calendar',
                isActive: $this->activeEmailTab === 'planned',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_email.tab_debugging'),
                target: $this->generateUrl('app_admin_email_debugging'),
                icon: 'bug',
                isActive: $this->activeEmailTab === 'debugging',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_email.tab_blocklist'),
                target: $this->generateUrl('app_admin_email_blocklist'),
                icon: 'ban',
                isActive: $this->activeEmailTab === 'blocklist',
            ),
        ]);
    }
}
