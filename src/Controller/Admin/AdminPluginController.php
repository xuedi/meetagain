<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\PluginService;
use Psalm\Plugin\PluginInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/plugin')]
class AdminPluginController extends AbstractController
{
    public function __construct(
        private readonly PluginService $pluginService,
        #[AutowireIterator(PluginInterface::class)]
        private readonly iterable $plugins,
    )
    {
    }

    #[Route('', name: 'app_admin_plugin')]
    public function list(): Response
    {
        return $this->render('admin/plugin/list.html.twig', [
            'loadedPlugins' => $this->plugins,
            'plugins' => $this->pluginService->getAdminList(),
            'active' => 'plugin',
        ]);
    }

    #[Route('/remove/{id}', name: 'admin_plugin_remove')]
    public function remove(int $id): Response
    {
        $this->pluginService->remove($id);

        return $this->redirectToRoute('app_admin_plugin');
    }

    #[Route('/install/step1/{name}', name: 'admin_plugin_install')]
    public function installStep1(string $name): Response
    {
        $this->pluginService->installStep1($name);

        return $this->redirectToRoute('admin_plugin_install_step2', ['name' => $name]);
    }

    #[Route('/install/step2/{name}', name: 'admin_plugin_install_step2')]
    public function installStep2(string $name): Response
    {
        $this->pluginService->installStep2($name);

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
