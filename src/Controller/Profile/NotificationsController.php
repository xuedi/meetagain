<?php

declare(strict_types=1);

namespace App\Controller\Profile;

use App\Controller\AbstractController;
use App\Service\Notification\User\NotificationService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class NotificationsController extends AbstractController
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    #[Route('/profile/notifications', name: 'app_profile_notifications')]
    public function index(): Response
    {
        $user = $this->getAuthedUser();
        $notifications = $this->notificationService->getNotifications($user);

        return $this->render('profile/notifications/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }
}
