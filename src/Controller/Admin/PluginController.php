<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminLink;
use App\Service\Admin\CommandService;
use App\Service\Config\PluginService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
final class PluginController extends AbstractAdminController
{
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

    public function __construct(
        private readonly PluginService $pluginService,
        private readonly CommandService $commandService,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('/admin/plugin', name: 'app_admin_plugin')]
    public function list(): Response
    {
        return $this->render('admin/system/plugin_list.html.twig', [
            'plugins' => $this->pluginService->getAdminList(),
            'active' => 'plugin',
        ]);
    }

    #[Route('/admin/plugin/install/{key}', name: 'admin_plugin_install', methods: ['POST'])]
    public function install(string $key): Response
    {
        try {
            $this->pluginService->install($key);
            $this->commandService->executeSubprocessMigrations();
            $this->addFlash('success', $this->translator->trans('admin_system.flash_plugin_installed', ['%plugin%' => $key]));
        } catch (\Throwable $e) {
            $this->addFlash('error', $this->translator->trans('admin_system.flash_plugin_install_failed', ['%error%' => $e->getMessage()]));
        }

        return $this->redirectToRoute('app_admin_plugin');
    }

    #[Route('/admin/plugin/uninstall/{key}', name: 'admin_plugin_uninstall', methods: ['POST'])]
    public function uninstall(string $key): Response
    {
        $this->pluginService->uninstall($key);
        $this->addFlash('success', $this->translator->trans('admin_system.flash_plugin_uninstalled', ['%plugin%' => $key]));

        return $this->redirectToRoute('app_admin_plugin');
    }

    #[Route('/admin/plugin/enable/{key}', name: 'admin_plugin_enable', methods: ['POST'])]
    public function enable(string $key): Response
    {
        $this->pluginService->enable($key);
        $this->addFlash('success', $this->translator->trans('admin_system.flash_plugin_enabled', ['%plugin%' => $key]));

        return $this->redirectToRoute('app_admin_plugin');
    }

    #[Route('/admin/plugin/disable/{key}', name: 'admin_plugin_disable', methods: ['POST'])]
    public function disable(string $key): Response
    {
        $this->pluginService->disable($key);
        $this->addFlash('success', $this->translator->trans('admin_system.flash_plugin_disabled', ['%plugin%' => $key]));

        return $this->redirectToRoute('app_admin_plugin');
    }
}
