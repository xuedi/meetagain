<?php declare(strict_types=1);

namespace App\AdminModules\Logs;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use Symfony\Bundle\SecurityBundle\Security;

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
        return 390; // After Logs in Logs section
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
                'path' => '/admin/visitors/',
                'controller' => [VisitorsController::class, 'index'],
            ],
        ];
    }

    public function isAccessible(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }
}
