<?php declare(strict_types=1);

namespace App\Controller\Admin\Seo;

use App\Admin\Navigation\AdminLink;
use App\Admin\Navigation\AdminNavigationConfig;
use App\Admin\Tabs\AdminTab;
use App\Admin\Tabs\AdminTabs;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractSeoController extends AbstractController
{
    public function __construct(
        protected readonly TranslatorInterface $translator,
        private readonly string $activeSeoTab,
    ) {}

    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'admin_shell.section_system',
            links: [
                new AdminLink(label: 'admin_shell.menu_seo', route: 'app_admin_seo', active: 'seo', role: 'ROLE_ADMIN'),
            ],
            sectionPriority: 100,
        );
    }

    final public function getTabs(): AdminTabs
    {
        return new AdminTabs([
            new AdminTab(
                label: $this->translator->trans('admin_seo.tab_dashboard'),
                target: $this->generateUrl('app_admin_seo'),
                icon: 'gauge',
                isActive: $this->activeSeoTab === 'dashboard',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_seo.tab_meta'),
                target: $this->generateUrl('app_admin_seo_meta'),
                icon: 'tags',
                isActive: $this->activeSeoTab === 'meta',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_seo.tab_sitemap'),
                target: $this->generateUrl('app_admin_seo_sitemap'),
                icon: 'sitemap',
                isActive: $this->activeSeoTab === 'sitemap',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_seo.tab_indexnow'),
                target: $this->generateUrl('app_admin_seo_indexnow'),
                icon: 'paper-plane',
                isActive: $this->activeSeoTab === 'indexnow',
            ),
            new AdminTab(
                label: $this->translator->trans('admin_seo.tab_canonical'),
                target: $this->generateUrl('app_admin_seo_canonical'),
                icon: 'link',
                isActive: $this->activeSeoTab === 'canonical',
            ),
        ]);
    }
}
