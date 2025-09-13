<?php declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\NotificationRsvp;
use App\Service\NotificationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class NotificationHandler
{
    public function __construct(private NotificationService $notificationService)
    {
    }

    public function __invoke(NotificationRsvp $payload): void
    {
        $this->notificationService->sendRsvp($payload->getUser(), $payload->getEvent());
    }
}
