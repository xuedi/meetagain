<?php declare(strict_types=1);

namespace App\Controller;

use App\Emails\Types\SupportEmailVerifyEmail;
use App\Entity\SupportRequest;
use App\Enum\SecurityEventType;
use App\Form\SupportThreadReplyType;
use App\Service\Email\BlocklistCheckerInterface;
use App\Service\Security\SecurityService;
use App\Service\Support\ThreadService;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/contact/request/{token}', requirements: ['token' => '[0-9a-f]{64}'])]
final class SupportThreadController extends AbstractController
{
    public function __construct(
        private readonly ThreadService $threadService,
        private readonly SecurityService $securityService,
        #[Autowire(service: 'limiter.support_thread_view')]
        private readonly RateLimiterFactoryInterface $viewLimiter,
        #[Autowire(service: 'limiter.support_thread_miss')]
        private readonly RateLimiterFactoryInterface $missLimiter,
        #[Autowire(service: 'limiter.support_thread_reply')]
        private readonly RateLimiterFactoryInterface $replyLimiter,
        #[Autowire(service: 'limiter.support_email_optin')]
        private readonly RateLimiterFactoryInterface $optinLimiter,
        #[Autowire(service: 'limiter.support_email_verify')]
        private readonly RateLimiterFactoryInterface $emailVerifyLimiter,
        private readonly SupportEmailVerifyEmail $supportEmailVerifyEmail,
        private readonly BlocklistCheckerInterface $blocklist,
    ) {}

    #[Route('', name: 'app_support_thread', methods: ['GET'])]
    public function show(Request $request, #[SensitiveParameter] string $token): Response
    {
        $rateLimited = $this->enforceLimiter($this->viewLimiter, $request);
        if ($rateLimited instanceof Response) {
            return $rateLimited;
        }

        $supportRequest = $this->resolveThread($request, $token);

        return $this->harden($this->render('support/thread.html.twig', [
            'supportRequest' => $supportRequest,
            'messages' => $this->threadService->getThread($supportRequest),
            'token' => $token,
            'replyForm' => $this->replyForm($supportRequest),
            'canReply' => $supportRequest->isOpenForRequester(),
        ]));
    }

    #[Route('/reply', name: 'app_support_thread_reply', methods: ['POST'])]
    public function reply(Request $request, #[SensitiveParameter] string $token): Response
    {
        $rateLimited = $this->enforceLimiter($this->replyLimiter, $request);
        if ($rateLimited instanceof Response) {
            return $rateLimited;
        }

        $supportRequest = $this->resolveThread($request, $token);

        if (!$supportRequest->isOpenForRequester()) {
            return $this->flashBack('support.flash_thread_closed', $token);
        }
        if ($this->threadService->isFull($supportRequest)) {
            return $this->flashBack('support.flash_thread_full', $token);
        }
        if ($this->threadService->isAwaitingAnswer($supportRequest)) {
            return $this->flashBack('support.flash_thread_awaiting_answer', $token);
        }

        $form = $this->replyForm($supportRequest);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->flashBack('support.flash_thread_reply_invalid', $token);
        }

        $this->threadService->postRequesterMessage($supportRequest, (string) $form->get('message')->getData(), $request->getClientIp());

        return $this->redirectToRoute('app_support_thread', ['token' => $token]);
    }

    #[Route('/subscribe', name: 'app_support_thread_subscribe', methods: ['POST'])]
    public function subscribe(Request $request, #[SensitiveParameter] string $token): Response
    {
        $rateLimited = $this->enforceLimiter($this->optinLimiter, $request);
        if ($rateLimited instanceof Response) {
            return $rateLimited;
        }

        $supportRequest = $this->resolveThread($request, $token);

        if (!$this->isCsrfTokenValid('app_support_thread_subscribe' . $token, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $email = mb_strtolower(trim((string) $request->request->get('support_optin_email')));
        if ($this->isMailable($supportRequest, $email) && $this->emailVerifyLimiter->create($email)->consume()->isAccepted()) {
            $verifyToken = $this->threadService->startEmailVerification($supportRequest, $email);
            $this->supportEmailVerifyEmail->send([
                'email' => $email,
                'token' => $verifyToken,
                'expiresAt' => $supportRequest->getEmailVerifyExpiresAt(),
                'lang' => $request->getLocale(),
            ]);
        }

        $this->addFlash('success', 'support.flash_optin_sent');

        return $this->redirectToRoute('app_support_thread', ['token' => $token]);
    }

    private function isMailable(SupportRequest $supportRequest, string $email): bool
    {
        return $email !== ''
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && !$this->blocklist->isBlocked($email)
            && !$supportRequest->isEmailVerified();
    }

    private function replyForm(SupportRequest $supportRequest): FormInterface
    {
        return $this->createForm(SupportThreadReplyType::class, null, [
            'csrf_token_id' => 'app_support_thread_reply' . $supportRequest->getId(),
        ]);
    }

    private function resolveThread(Request $request, #[SensitiveParameter] string $token): SupportRequest
    {
        $supportRequest = $this->threadService->findByToken($token);
        if ($supportRequest instanceof SupportRequest) {
            return $supportRequest;
        }

        if (!$this->missLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            $this->securityService->event(SecurityEventType::RateLimit, $request, ['limiter' => 'support_thread_miss']);
        }

        throw $this->createNotFoundException();
    }

    private function enforceLimiter(RateLimiterFactoryInterface $factory, Request $request): ?Response
    {
        if ($factory->create($request->getClientIp())->consume()->isAccepted()) {
            return null;
        }

        $this->securityService->event(SecurityEventType::RateLimit, $request, ['limiter' => 'support_thread']);

        return $this->render('rate_limited.html.twig', ['message' => 'support.rate_limited_message'], new Response('', 429));
    }

    private function flashBack(string $messageKey, #[SensitiveParameter] string $token): Response
    {
        $this->addFlash('error', $messageKey);

        return $this->redirectToRoute('app_support_thread', ['token' => $token]);
    }

    private function harden(Response $response): Response
    {
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
