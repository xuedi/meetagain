<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminLink;
use App\Service\CommandService;
use App\Service\PluginService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class PluginController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(section: 'System', links: [
            new AdminLink(label: 'menu_admin_plugin', route: 'app_admin_plugin', active: 'plugin', role: 'ROLE_ADMIN'),
        ]);
    }

    public function __construct(
        private readonly PluginService $pluginService,
        private readonly CommandService $commandService,
    ) {}

    #[Route('/admin/plugin', name: 'app_admin_plugin')]
    public function list(): Response
    {
        return $this->render('admin/system/plugin_list.html.twig', [
            'plugins' => $this->pluginService->getAdminList(),
            'active' => 'plugin',
        ]);
    }

    #[Route('/admin/plugin/install/{name}', name: 'admin_plugin_install', methods: ['POST'])]
    public function install(string $name): Response
    {
        $this->pluginService->install($name);

        return $this->redirectToRoute('app_admin_plugin');
    }

    #[Route('/admin/plugin/uninstall/{name}', name: 'admin_plugin_uninstall', methods: ['POST'])]
    public function uninstall(string $name): Response
    {
        $this->pluginService->uninstall($name);

        return $this->redirectToRoute('app_admin_plugin');
    }

    #[Route('/admin/plugin/enable/{name}', name: 'admin_plugin_enable', methods: ['POST'])]
    public function enable(string $name): Response
    {
        $this->pluginService->enable($name);

        return $this->redirectToRoute('admin_plugin_migrate');
    }

    #[Route('/admin/plugin/migrate', name: 'admin_plugin_migrate', methods: ['POST'])]
    public function migrate(): Response
    {
        $this->commandService->executeMigrations();

        return $this->redirectToRoute('app_admin_plugin');
    }

    #[Route('/admin/plugin/disable/{name}', name: 'admin_plugin_disable', methods: ['POST'])]
    public function disable(string $name): Response
    {
        $this->pluginService->disable($name);

        return $this->redirectToRoute('app_admin_plugin');
    }
}
