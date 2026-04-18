<?php

declare(strict_types=1);

namespace App\Controller\Profile;

use App\Controller\AbstractController;
use App\Service\Notification\User\NotificationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class NotificationsController extends AbstractController
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    #[Route('/profile/notifications', name: 'app_profile_notifications')]
    public function index(Request $request): Response
    {
        if (!$this->getUser() instanceof UserInterface) {
            $request->getSession()->set('redirectUrl', $request->getRequestUri());
            return $this->redirectToRoute('app_login');
        }

        $user = $this->getAuthedUser();
        $notifications = $this->notificationService->getNotifications($user);

        return $this->render('profile/notifications/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }
}
