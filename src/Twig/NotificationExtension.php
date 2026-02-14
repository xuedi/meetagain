<?php declare(strict_types=1);

namespace App\Twig;

use App\Notification\NotificationService;
use App\Notification\NotificationSummary;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly Security $security,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_notifications', $this->getNotifications(...)),
        ];
    }

    public function getNotifications(): NotificationSummary
    {
        $user = $this->security->getUser();

        return $user ? $this->notificationService->getNotifications($user) : new NotificationSummary([], 0);
    }
}
