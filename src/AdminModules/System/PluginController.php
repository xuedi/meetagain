<?php declare(strict_types=1);

namespace App\AdminModules\System;

use App\Service\CommandService;
use App\Service\PluginService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class PluginController extends AbstractController
{
    public function __construct(
        private readonly PluginService $pluginService,
        private readonly CommandService $commandService,
    ) {}

    public function list(): Response
    {
        return $this->render('admin_modules/system/plugin_list.html.twig', [
            'plugins' => $this->pluginService->getAdminList(),
            'active' => 'plugin',
        ]);
    }

    public function install(string $name): Response
    {
        $this->pluginService->install($name);

        return $this->redirectToRoute('app_admin_plugin');
    }

    public function uninstall(string $name): Response
    {
        $this->pluginService->uninstall($name);

        return $this->redirectToRoute('app_admin_plugin');
    }

    public function enable(string $name): Response
    {
        $this->pluginService->enable($name);

        return $this->redirectToRoute('admin_plugin_migrate');
    }

    public function migrate(): Response
    {
        $this->commandService->executeMigrations();

        return $this->redirectToRoute('app_admin_plugin');
    }

    public function disable(string $name): Response
    {
        $this->pluginService->disable($name);

        return $this->redirectToRoute('app_admin_plugin');
    }
}
