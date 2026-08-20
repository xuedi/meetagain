<?php declare(strict_types=1);

namespace App\Controller;

use App\Emails\Types\SupportNotificationEmail;
use App\EntityActionDispatcher;
use App\Entity\SupportRequest;
use App\Entity\User;
use App\Enum\EntityAction;
use App\Enum\SecurityEventType;
use App\Enum\SupportRequestStatus;
use App\Form\SupportRequestType;
use App\Service\Member\CaptchaService;
use App\Service\Security\ContentSanitizer;
use App\Service\Security\SecurityService;
use App\Service\Support\ThreadService;
use DateTimeImmutable;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class SupportController extends AbstractController
{
    public function __construct(
        private readonly SupportNotificationEmail $supportNotificationEmail,
        private readonly CaptchaService $captchaService,
        #[Autowire(service: 'limiter.support')]
        private readonly RateLimiterFactoryInterface $supportLimiter,
        private readonly SecurityService $securityService,
        private readonly ContentSanitizer $contentSanitizer,
        private readonly ThreadService $threadService,
        private readonly EntityActionDispatcher $entityActionDispatcher,
    ) {}

    #[Route('/support', name: 'app_support_redirect')]
    public function supportRedirect(): Response
    {
        return $this->redirectToRoute('app_contact', [], 301);
    }

    #[Route('/contact/verify/{token}', name: 'app_support_email_verify', requirements: ['token' => '[0-9a-f]{64}'], methods: ['GET'])]
    public function verifyEmail(#[SensitiveParameter] string $token): Response
    {
        $confirmed = $this->threadService->confirmEmail($token);
        if (!$confirmed instanceof SupportRequest) {
            throw $this->createNotFoundException();
        }

        return $this->render('support/email_verified.html.twig');
    }

    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request): Response
    {
        $limiter = $this->supportLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            $this->securityService->event(SecurityEventType::RateLimit, $request, ['limiter' => 'support']);
            return $this->render(
                'rate_limited.html.twig',
                [
                    'message' => 'support.rate_limited_message',
                ],
                new Response('', 429),
            );
        }

        $user = $this->getUser();
        $isGuest = !$user instanceof User;

        $form = $this->createForm(SupportRequestType::class, null, ['guest' => $isGuest]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($isGuest) {
                $captchaError = $this->captchaService->isValid((string) $form->get('captcha')->getData());
                if ($captchaError !== null) {
                    $form->get('captcha')->addError(new FormError($captchaError));
                    $this->captchaService->reset();
                }
            }

            if ($form->getErrors(true)->count() === 0) {
                $supportRequest = new SupportRequest();
                $supportRequest->setRequester($isGuest ? null : $user);
                $supportRequest->setAudience($form->get('audience')->getData());
                $supportRequest->setMessage($this->contentSanitizer->escape((string) $form->get('message')->getData()));
                $supportRequest->setCreatedAt(new DateTimeImmutable());
                $supportRequest->setStatus(SupportRequestStatus::New);
                $supportRequest->setIpAddress($request->getClientIp());

                $token = $this->threadService->openThread($supportRequest, $request->getClientIp());

                $this->entityActionDispatcher->dispatch(EntityAction::CreateSupportRequest, (int) $supportRequest->getId());
                $this->supportNotificationEmail->send(['request' => $supportRequest]);

                if ($token === null) {
                    return $this->render('support/submitted.html.twig');
                }

                $this->addFlash('success', 'support.flash_request_received');

                return $this->redirectToRoute('app_support_thread', ['token' => $token]);
            }
        }

        if (!$isGuest) {
            return $this->render('support/index.html.twig', ['form' => $form, 'captcha' => null]);
        }

        $this->captchaService->reset();
        return $this->render('support/index.html.twig', [
            'form' => $form,
            'captcha' => $this->captchaService->generate(),
            'refreshCount' => $this->captchaService->getRefreshCount(),
            'refreshTime' => $this->captchaService->getRefreshTime(),
        ]);
    }
}
