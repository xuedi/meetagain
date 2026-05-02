<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Admin\Navigation\AdminLink;
use App\Admin\Navigation\AdminNavigationConfig;
use App\Admin\Tabs\AdminTab;
use App\Admin\Tabs\AdminTabs;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractSettingsController extends AbstractController
{
    public function __construct(
        protected readonly TranslatorInterface $translator,
        private readonly string $activeSettingsTab,
    ) {}

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

    final public function getTabs(): AdminTabs
    {
        return new AdminTabs([
            new AdminTab(
                label: $this->translator->trans('admin_system.tab_config'),
                target: $this->generateUrl('app_admin_system_config'),
                icon: 'cog',
                isActive: $this->activeSettingsTab === 'config',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_system.tab_theme'),
                target: $this->generateUrl('app_admin_system_theme'),
                icon: 'palette',
                isActive: $this->activeSettingsTab === 'theme',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_system.tab_images'),
                target: $this->generateUrl('app_admin_system_images'),
                icon: 'image',
                isActive: $this->activeSettingsTab === 'images',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_system.tab_language'),
                target: $this->generateUrl('app_admin_language'),
                icon: 'language',
                isActive: $this->activeSettingsTab === 'language',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_system.tab_permissions'),
                target: $this->generateUrl('app_admin_system_permissions'),
                icon: 'shield-alt',
                isActive: $this->activeSettingsTab === 'permissions',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_system.tab_seo'),
                target: $this->generateUrl('app_admin_system_seo'),
                icon: 'search',
                isActive: $this->activeSettingsTab === 'seo',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_system.tab_sitemap'),
                target: $this->generateUrl('app_admin_system_sitemap'),
                icon: 'sitemap',
                isActive: $this->activeSettingsTab === 'sitemap',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_system.tab_import'),
                target: $this->generateUrl('app_admin_system_import'),
                icon: 'file-import',
                isActive: $this->activeSettingsTab === 'import',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_system.tab_debug'),
                target: $this->generateUrl('app_admin_system_debug'),
                icon: 'bug',
                isActive: $this->activeSettingsTab === 'debug',
            ),
        ]);
    }
}
