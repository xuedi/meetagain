<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\PluginService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/plugin')]
class AdminPluginController extends AbstractController
{
    public function __construct(
        private readonly PluginService $pluginService,
    ) {}

    #[Route('', name: 'app_admin_plugin')]
    public function list(): Response
    {
        return $this->render('admin/plugin/list.html.twig', [
            'plugins' => $this->pluginService->getAdminList(),
            'active' => 'plugin',
        ]);
    }

    #[Route('/install/{name}', name: 'admin_plugin_install')]
    public function install(string $name): Response
    {
        $this->pluginService->install($name);

        return $this->redirectToRoute('app_admin_plugin');
    }

    #[Route('/uninstall/{name}', name: 'admin_plugin_uninstall')]
    public function uninstall(string $name): Response
    {
        $this->pluginService->uninstall($name);

        return $this->redirectToRoute('app_admin_plugin');
    }

    #[Route('/enable/{name}', name: 'admin_plugin_enable')]
    public function enable(string $name): Response
    {
        $this->pluginService->enable($name);

        return $this->redirectToRoute('app_admin_plugin');
    }

    #[Route('/disable/{name}', name: 'admin_plugin_disable')]
    public function disable(string $name): Response
    {
        $this->pluginService->disable($name);

        return $this->redirectToRoute('app_admin_plugin');
    }
}
