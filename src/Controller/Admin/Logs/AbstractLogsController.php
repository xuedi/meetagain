<?php declare(strict_types=1);

namespace App\Controller\Admin\Logs;

use App\Admin\Navigation\AdminLink;
use App\Admin\Navigation\AdminNavigationConfig;
use App\Admin\Tabs\AdminTab;
use App\Admin\Tabs\AdminTabs;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractLogsController extends AbstractController
{
    public function __construct(
        protected readonly TranslatorInterface $translator,
        private readonly string $activeLogsTab,
    ) {}

    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'admin_shell.section_system',
            links: [
                new AdminLink(
                    label: 'admin_shell.menu_logs',
                    route: 'app_admin_activity_log',
                    active: 'logs',
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
                label: $this->translator->trans('admin_logs.tab_activity'),
                target: $this->generateUrl('app_admin_activity_log'),
                icon: 'list',
                isActive: $this->activeLogsTab === 'activity',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_logs.tab_system'),
                target: $this->generateUrl('app_admin_system_log'),
                icon: 'file-alt',
                isActive: $this->activeLogsTab === 'system',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_logs.tab_404'),
                target: $this->generateUrl('app_admin_not_found_log'),
                icon: 'exclamation-triangle',
                isActive: $this->activeLogsTab === 'not_found',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_logs.tab_cron'),
                target: $this->generateUrl('app_admin_cron_log'),
                icon: 'clock',
                isActive: $this->activeLogsTab === 'cron',
            ),
        ]);
    }
}
