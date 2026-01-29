<?php declare(strict_types=1);

namespace App\AdminModules\Logs;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use App\Entity\User;
use App\Entity\UserRole;
use App\Security\Attribute\RequiresRole;
use Symfony\Bundle\SecurityBundle\Security;

#[RequiresRole(UserRole::Admin)]
readonly class VisitorsModule implements AdminModuleInterface
{
    public function __construct(
        private Security $security,
    ) {}

    public function getKey(): string
    {
        return 'visitors';
    }

    public function getPriority(): int
    {
        return 390; // After Activity Log in Logs section
    }

    public function getSectionName(): string
    {
        return 'Logs';
    }

    public function getLinks(): array
    {
        return [
            new AdminLink(label: 'menu_admin_visitors', route: 'app_admin_visitors', active: 'visitors'),
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'name' => 'app_admin_visitors',
                'path' => '/admin/visitors',
                'controller' => [VisitorsController::class, 'visitorApprovalList'],
            ],
            [
                'name' => 'app_admin_visitors_approve',
                'path' => '/admin/visitors/approve/{id}',
                'controller' => [VisitorsController::class, 'visitorApprove'],
                'methods' => ['POST'],
            ],
            [
                'name' => 'app_admin_visitors_reject',
                'path' => '/admin/visitors/reject/{id}',
                'controller' => [VisitorsController::class, 'visitorReject'],
                'methods' => ['POST'],
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
