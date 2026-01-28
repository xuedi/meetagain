<?php declare(strict_types=1);

namespace App\AdminModules\Tables;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use Symfony\Bundle\SecurityBundle\Security;

readonly class EventModule implements AdminModuleInterface
{
    public function __construct(
        private Security $security,
    ) {}

    public function getKey(): string
    {
        return 'event';
    }

    public function getPriority(): int
    {
        return 800; // First in Tables section
    }

    public function getSectionName(): string
    {
        return 'Tables';
    }

    public function getLinks(): array
    {
        return [
            new AdminLink(label: 'menu_admin_event', route: 'app_admin_event', active: 'event'),
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'name' => 'app_admin_event',
                'path' => '/admin/event/',
                'controller' => [EventController::class, 'eventList'],
            ],
            [
                'name' => 'app_admin_event_add',
                'path' => '/admin/event/add',
                'controller' => [EventController::class, 'eventAdd'],
                'methods' => ['GET', 'POST'],
            ],
            [
                'name' => 'app_admin_event_edit',
                'path' => '/admin/event/{id}/edit',
                'controller' => [EventController::class, 'eventEdit'],
                'methods' => ['GET', 'POST'],
            ],
            [
                'name' => 'app_admin_event_delete',
                'path' => '/admin/event/{id}/delete',
                'controller' => [EventController::class, 'eventDelete'],
                'methods' => ['POST'],
            ],
            [
                'name' => 'app_admin_event_cancel',
                'path' => '/admin/event/{id}/cancel',
                'controller' => [EventController::class, 'eventCancel'],
                'methods' => ['POST'],
            ],
            [
                'name' => 'app_admin_event_uncancel',
                'path' => '/admin/event/{id}/uncancel',
                'controller' => [EventController::class, 'eventUncancel'],
                'methods' => ['POST'],
            ],
        ];
    }

    public function isAccessible(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }
}
