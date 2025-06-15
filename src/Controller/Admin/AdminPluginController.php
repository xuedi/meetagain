<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\PluginService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminPluginController extends AbstractController
{
    public function __construct(private readonly PluginService $pluginService)
    {
    }
    #[Route('/admin/plugin', name: 'app_admin_plugin')]
    public function list(): Response
    {
        return $this->render('admin/plugin/list.html.twig', [
            'active' => 'plugin',
            'plugins' => $this->pluginService->getAdminList(),
        ]);
    }

    #[Route('/admin/plugin/remove/{id}', name: 'admin_plugin_remove')]
    public function remove(int $id): Response
    {
        $this->pluginService->remove($id);

        return $this->redirectToRoute('app_admin_plugin');
    }

    #[Route('/admin/plugin/install/{ident}', name: 'admin_plugin_install')]
    public function install(string $ident): Response
    {
        $this->pluginService->install($ident);

        return $this->redirectToRoute('app_admin_plugin');
    }

    #[Route('/admin/plugin/uninstall/{ident}', name: 'admin_plugin_uninstall')]
    public function uninstall(string $ident): Response
    {
        $this->pluginService->uninstall($ident);

        return $this->redirectToRoute('app_admin_plugin');
    }

    #[Route('/admin/plugin/enable/{ident}', name: 'admin_plugin_enable')]
    public function enable(string $ident): Response
    {
        $this->pluginService->enable($ident);

        return $this->redirectToRoute('app_admin_plugin');
    }

    #[Route('/admin/plugin/disable/{ident}', name: 'admin_plugin_disable')]
    public function disable(string $ident): Response
    {
        $this->pluginService->disable($ident);

        return $this->redirectToRoute('app_admin_plugin');
    }
}
