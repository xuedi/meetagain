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

    #[Route('/admin/plugin/install/step1/{name}', name: 'admin_plugin_install')]
    public function installStep1(string $name): Response
    {
        $this->pluginService->installStep1($name);

        return $this->redirectToRoute('admin_plugin_install_step2', ['name' => $name]);
    }

    #[Route('/admin/plugin/install/step2/{name}', name: 'admin_plugin_install_step2')]
    public function installStep2(string $name): Response
    {
        $this->pluginService->installStep2($name);

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
