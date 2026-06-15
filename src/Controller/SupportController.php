<?php declare(strict_types=1);

namespace App\Controller;

use App\Emails\Types\SupportNotificationEmail;
use App\Entity\SupportRequest;
use App\Entity\User;
use App\Enum\SecurityEventType;
use App\Enum\SupportRequestStatus;
use App\Form\SupportRequestType;
use App\Service\Member\CaptchaService;
use App\Service\Security\ContentSanitizer;
use App\Service\Security\SecurityService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
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
    ) {}

    #[Route('/support', name: 'app_support_redirect')]
    public function supportRedirect(): Response
    {
        return $this->redirectToRoute('app_contact', [], 301);
    }

    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request, EntityManagerInterface $em): Response
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
        $defaultData = $user instanceof User ? ['name' => $user->getName(), 'email' => $user->getEmail()] : [];

        $form = $this->createForm(SupportRequestType::class, $defaultData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isGuest = !$user instanceof User;

            if ($isGuest) {
                $captcha = $form->get('captcha')->getData();
                $captchaError = $this->captchaService->isValid($captcha);
                if ($captchaError !== null) {
                    $form->get('captcha')->addError(new FormError($captchaError));
                    $this->captchaService->reset();
                }
            }

            if ($form->getErrors(true)->count() === 0) {
                $supportRequest = new SupportRequest();
                $supportRequest->setName($this->contentSanitizer->toPlainText($user instanceof User ? (string) $user->getName() : (string) $form->get('name')->getData()));
                $supportRequest->setEmail($user instanceof User ? (string) $user->getEmail() : (string) $form->get('email')->getData());
                $supportRequest->setContactType($form->get('contactType')->getData());
                $supportRequest->setMessage($this->contentSanitizer->escape((string) $form->get('message')->getData()));
                $supportRequest->setCreatedAt(new DateTimeImmutable());
                $supportRequest->setStatus(SupportRequestStatus::New);
                $supportRequest->setIpAddress($request->getClientIp());

                $em->persist($supportRequest);
                $em->flush();

                $this->supportNotificationEmail->send(['request' => $supportRequest]);

                // Intentionally not a PRG redirect: the confirmation state carries no live form,
                // so a browser refresh cannot replay the submission.
                return $this->render('support/index.html.twig', ['submitted' => true]);
            }
        }

        $this->captchaService->reset();
        return $this->render('support/index.html.twig', [
            'submitted' => false,
            'form' => $form,
            'captcha' => $this->captchaService->generate(),
            'refreshCount' => $this->captchaService->getRefreshCount(),
            'refreshTime' => $this->captchaService->getRefreshTime(),
        ]);
    }
}
