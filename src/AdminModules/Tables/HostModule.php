<?php declare(strict_types=1);

namespace App\AdminModules\Tables;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use Symfony\Bundle\SecurityBundle\Security;

readonly class HostModule implements AdminModuleInterface
{
    public function __construct(
        private Security $security,
    ) {}

    public function getKey(): string
    {
        return 'host';
    }

    public function getPriority(): int
    {
        return 780; // After Event and Location in Tables section
    }

    public function getSectionName(): string
    {
        return 'Tables';
    }

    public function getLinks(): array
    {
        return [
            new AdminLink(label: 'menu_admin_host', route: 'app_admin_host', active: 'host'),
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'name' => 'app_admin_host',
                'path' => '/admin/host',
                'controller' => [HostController::class, 'hostList'],
            ],
            [
                'name' => 'app_admin_host_add',
                'path' => '/admin/host/add',
                'controller' => [HostController::class, 'hostAdd'],
                'methods' => ['GET', 'POST'],
            ],
            [
                'name' => 'app_admin_host_edit',
                'path' => '/admin/host/edit/{id}',
                'controller' => [HostController::class, 'hostEdit'],
                'methods' => ['GET', 'POST'],
            ],
        ];
    }

    public function isAccessible(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }
}
