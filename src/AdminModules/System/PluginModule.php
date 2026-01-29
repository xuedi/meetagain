<?php declare(strict_types=1);

namespace App\AdminModules\System;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use App\Entity\User;
use App\Entity\UserRole;
use App\Security\Attribute\RequiresRole;
use Symfony\Bundle\SecurityBundle\Security;

#[RequiresRole(UserRole::Admin)]
readonly class PluginModule implements AdminModuleInterface
{
    public function __construct(
        private Security $security,
    ) {}

    public function getKey(): string
    {
        return 'plugin';
    }

    public function getPriority(): int
    {
        return 990; // Right after System settings
    }

    public function getSectionName(): string
    {
        return 'System';
    }

    public function getLinks(): array
    {
        return [
            new AdminLink(label: 'menu_admin_plugin', route: 'app_admin_plugin', active: 'plugin'),
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'name' => 'app_admin_plugin',
                'path' => '/admin/plugin',
                'controller' => [PluginController::class, 'list'],
            ],
            [
                'name' => 'admin_plugin_install',
                'path' => '/admin/plugin/install/{name}',
                'controller' => [PluginController::class, 'install'],
                'methods' => ['POST'],
            ],
            [
                'name' => 'admin_plugin_uninstall',
                'path' => '/admin/plugin/uninstall/{name}',
                'controller' => [PluginController::class, 'uninstall'],
                'methods' => ['POST'],
            ],
            [
                'name' => 'admin_plugin_enable',
                'path' => '/admin/plugin/enable/{name}',
                'controller' => [PluginController::class, 'enable'],
                'methods' => ['POST'],
            ],
            [
                'name' => 'admin_plugin_migrate',
                'path' => '/admin/plugin/migrate',
                'controller' => [PluginController::class, 'migrate'],
                'methods' => ['POST'],
            ],
            [
                'name' => 'admin_plugin_disable',
                'path' => '/admin/plugin/disable/{name}',
                'controller' => [PluginController::class, 'disable'],
                'methods' => ['POST'],
            ],
        ];
    }

    public function isAccessible(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof \App\Entity\User) {
            return false;
        }
        return $user->hasUserRole(\App\Entity\UserRole::Admin);
    }
}
