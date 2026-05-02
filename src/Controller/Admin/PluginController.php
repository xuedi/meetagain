<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\Navigation\AdminLink;
use App\Admin\Navigation\AdminNavigationConfig;
use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Service\Admin\CommandService;
use App\Service\Config\PluginService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
final class PluginController extends AbstractController implements AdminNavigationInterface
{
    public function __construct(
        private readonly PluginService $pluginService,
        private readonly CommandService $commandService,
        private readonly TranslatorInterface $translator,
    ) {}

    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'admin_shell.section_system',
            links: [
                new AdminLink(
                    label: 'admin_shell.menu_plugin',
                    route: 'app_admin_plugin',
                    active: 'plugin',
                    role: 'ROLE_ADMIN',
                ),
            ],
            sectionPriority: 100,
        );
    }

    #[Route('/admin/plugin', name: 'app_admin_plugin')]
    public function list(): Response
    {
        $plugins = $this->pluginService->getAdminList();
        $totalCount = count($plugins);
        $installedCount = count(array_filter($plugins, static fn (array $p): bool => (bool) ($p['installed'] ?? false)));
        $enabledCount = count(array_filter($plugins, static fn (array $p): bool => (bool) ($p['enabled'] ?? false)));

        $adminTop = new AdminTop(
            info: [
                new AdminTopInfoHtml(sprintf(
                    '<strong>%d</strong>&nbsp;%s',
                    $totalCount,
                    $this->translator->trans('admin_system_plugins.summary_available'),
                )),
                new AdminTopInfoHtml(sprintf(
                    '<strong>%d</strong>&nbsp;%s',
                    $installedCount,
                    $this->translator->trans('admin_system_plugins.summary_installed'),
                )),
                new AdminTopInfoHtml(sprintf(
                    '<span class="tag is-success is-medium">%d&nbsp;%s</span>',
                    $enabledCount,
                    $this->translator->trans('admin_system_plugins.summary_enabled'),
                )),
            ],
        );

        return $this->render('admin/system/plugin_list.html.twig', [
            'plugins' => $plugins,
            'active' => 'plugin',
            'adminTop' => $adminTop,
        ]);
    }

    #[Route('/admin/plugin/install/{key}', name: 'admin_plugin_install', methods: ['POST'])]
    public function install(string $key): Response
    {
        try {
            $this->pluginService->install($key);
            $this->commandService->executeSubprocessMigrations();
            $this->addFlash('success', $this->translator->trans('admin_system_plugins.flash_installed', ['%plugin%' => $key]));
        } catch (\Throwable $e) {
            $this->addFlash('error', $this->translator->trans('admin_system_plugins.flash_install_failed', ['%error%' => $e->getMessage()]));
        }

        return $this->redirectToRoute('app_admin_plugin');
    }

    #[Route('/admin/plugin/uninstall/{key}', name: 'admin_plugin_uninstall', methods: ['POST'])]
    public function uninstall(string $key): Response
    {
        $this->pluginService->uninstall($key);
        $this->addFlash('success', $this->translator->trans('admin_system_plugins.flash_uninstalled', ['%plugin%' => $key]));

        return $this->redirectToRoute('app_admin_plugin');
    }

    #[Route('/admin/plugin/enable/{key}', name: 'admin_plugin_enable', methods: ['POST'])]
    public function enable(string $key): Response
    {
        $this->pluginService->enable($key);
        $this->addFlash('success', $this->translator->trans('admin_system_plugins.flash_enabled', ['%plugin%' => $key]));

        return $this->redirectToRoute('app_admin_plugin');
    }

    #[Route('/admin/plugin/disable/{key}', name: 'admin_plugin_disable', methods: ['POST'])]
    public function disable(string $key): Response
    {
        $this->pluginService->disable($key);
        $this->addFlash('success', $this->translator->trans('admin_system_plugins.flash_disabled', ['%plugin%' => $key]));

        return $this->redirectToRoute('app_admin_plugin');
    }
}
