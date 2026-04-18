<?php

declare(strict_types=1);

namespace App\Controller\Profile;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Service\Notification\User\ReviewNotificationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[IsGranted('ROLE_USER')]
final class ReviewController extends AbstractController
{
    public function __construct(
        private readonly ReviewNotificationService $service,
    ) {}

    #[Route('/profile/review', name: 'app_profile_review', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        $providers = $this->service->getProvidersForUser($user);

        return $this->render('profile/review/index.html.twig', [
            'providers' => $providers,
        ]);
    }

    #[Route('/profile/review/{providerIdentifier}/approve/{itemId}', name: 'app_profile_review_approve', methods: ['POST'])]
    public function approve(Request $request, string $providerIdentifier, string $itemId, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('review_action', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('app_profile_review');
        }

        try {
            $this->service->getProviderByIdentifier($providerIdentifier)->approveItem($user, $itemId);
        } catch (Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_profile_review');
    }

    #[Route('/profile/review/{providerIdentifier}/deny/{itemId}', name: 'app_profile_review_deny', methods: ['POST'])]
    public function deny(Request $request, string $providerIdentifier, string $itemId, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('review_action', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('app_profile_review');
        }

        try {
            $this->service->getProviderByIdentifier($providerIdentifier)->denyItem($user, $itemId);
        } catch (Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_profile_review');
    }
}
