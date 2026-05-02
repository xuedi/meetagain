<?php declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Admin\Tabs\AdminTab;
use App\Admin\Tabs\AdminTabs;
use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractEmailController extends AbstractAdminController
{
    public function __construct(
        protected readonly TranslatorInterface $translator,
        private readonly string $activeEmailTab,
    ) {}

    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
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
