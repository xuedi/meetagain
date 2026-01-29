<?php declare(strict_types=1);

namespace App\AdminModules\Logs;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use App\Entity\User;
use App\Entity\UserRole;
use App\Security\Attribute\RequiresRole;
use Symfony\Bundle\SecurityBundle\Security;

#[RequiresRole(UserRole::Admin)]
readonly class LogsModule implements AdminModuleInterface
{
    public function __construct(
        private Security $security,
    ) {}

    public function getKey(): string
    {
        return 'activity_log';
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
            new AdminLink(label: 'menu_admin_activity_log', route: 'app_admin_activity_log', active: 'activity_log'),
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'name' => 'app_admin_activity_log',
                'path' => '/admin/logs/activity',
                'controller' => [LogsController::class, 'activityLog'],
            ],
            [
                'name' => 'app_admin_system_log',
                'path' => '/admin/logs/system',
                'controller' => [LogsController::class, 'systemLog'],
            ],
            [
                'name' => 'app_admin_not_found_log',
                'path' => '/admin/logs/not-found',
                'controller' => [LogsController::class, 'notFoundLog'],
            ],
        ];
    }

    public function isAccessible(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }
        return $user->hasUserRole(UserRole::Admin);
    }
}
