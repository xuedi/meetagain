<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Service\PluginService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminPluginController extends AbstractController
{
    public function __construct(
        private readonly PluginService $pluginService,
    ) {
    }

    #[Route('/admin/plugin', name: 'app_admin_plugin')]
    public function list(): Response
    {
        return $this->render('admin/plugin/list.html.twig', [
            'plugins' => $this->pluginService->getAdminList(),
            'active' => 'plugin',
        ]);
    }

    #[Route('/admin/plugin/install/{name}', name: 'admin_plugin_install')]
    public function install(string $name): Response
    {
        $this->pluginService->install($name);

        return $this->redirectToRoute('app_admin_plugin');
    }

    #[Route('/admin/plugin/uninstall/{name}', name: 'admin_plugin_uninstall')]
    public function uninstall(string $name): Response
    {
        $this->pluginService->uninstall($name);

        return $this->redirectToRoute('app_admin_plugin');
    }

    #[Route('/admin/plugin/enable/{name}', name: 'admin_plugin_enable')]
    public function enable(string $name): Response
    {
        $this->pluginService->enable($name);

        return $this->redirectToRoute('app_admin_plugin');
    }

    #[Route('/admin/plugin/disable/{name}', name: 'admin_plugin_disable')]
    public function disable(string $name): Response
    {
        $this->pluginService->disable($name);

        return $this->redirectToRoute('app_admin_plugin');
    }
}
