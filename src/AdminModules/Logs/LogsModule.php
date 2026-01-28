<?php declare(strict_types=1);

namespace App\AdminModules\Logs;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use Symfony\Bundle\SecurityBundle\Security;

readonly class LogsModule implements AdminModuleInterface
{
    public function __construct(
        private Security $security,
    ) {}

    public function getKey(): string
    {
        return 'logs';
    }

    public function getPriority(): int
    {
        return 400; // Start of Logs section
    }

    public function getSectionName(): string
    {
        return 'Logs';
    }

    public function getLinks(): array
    {
        return [
            new AdminLink(label: 'menu_admin_logs_activity', route: 'app_admin_logs_activity', active: 'activity'),
            new AdminLink(label: 'menu_admin_logs_system', route: 'app_admin_logs_system', active: 'logs'),
            new AdminLink(label: 'menu_admin_logs_404', route: 'app_admin_logs_not_found', active: '404'),
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'name' => 'app_admin_logs_activity',
                'path' => '/admin/logs/activity',
                'controller' => [LogsController::class, 'activityList'],
            ],
            [
                'name' => 'app_admin_logs_system',
                'path' => '/admin/logs/system',
                'controller' => [LogsController::class, 'systemLogs'],
            ],
            [
                'name' => 'app_admin_logs_not_found',
                'path' => '/admin/logs/404',
                'controller' => [LogsController::class, 'notFoundLogs'],
            ],
        ];
    }

    public function isAccessible(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }
}
