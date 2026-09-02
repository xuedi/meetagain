<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\Notification\User\NotificationService;
use App\Service\Notification\User\NotificationSummary;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class NotificationRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private NotificationService $notificationService,
        private Security $security,
    ) {}

    public function getNotifications(): NotificationSummary
    {
        $user = $this->security->getUser();

        return $user ? $this->notificationService->getNotifications($user) : new NotificationSummary([], 0);
    }
}
