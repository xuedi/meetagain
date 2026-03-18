<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\SupportRequest;
use App\Entity\User;
use App\Enum\SupportRequestStatus;
use App\Form\SupportRequestType;
use App\Service\Email\EmailService;
use App\Service\Member\CaptchaService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class SupportController extends AbstractController
{
    public function __construct(
        private readonly EmailService $emailService,
        private readonly CaptchaService $captchaService,
        #[Autowire(service: 'limiter.support')]
        private readonly RateLimiterFactory $supportLimiter,
    ) {}

    #[Route('/support', name: 'app_support')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $limiter = $this->supportLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->render(
                'rate_limited.html.twig',
                [
                    'message' => 'Too many support requests. Please try again later.',
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
                $supportRequest->setName(
                    $user instanceof User ? (string) $user->getName() : (string) $form->get('name')->getData(),
                );
                $supportRequest->setEmail(
                    $user instanceof User ? (string) $user->getEmail() : (string) $form->get('email')->getData(),
                );
                $supportRequest->setMessage((string) $form->get('message')->getData());
                $supportRequest->setCreatedAt(new DateTimeImmutable());
                $supportRequest->setStatus(SupportRequestStatus::New);
                $supportRequest->setIpAddress($request->getClientIp());

                $em->persist($supportRequest);
                $em->flush();

                $this->emailService->prepareSupportNotification($supportRequest);
                $this->emailService->sendQueue();

                $this->addFlash('success', 'Your message has been sent. We will get back to you shortly.');

                return $this->redirectToRoute('app_support');
            }
        } else {
            $this->captchaService->reset();
        }

        return $this->render('support/index.html.twig', [
            'form' => $form,
            'captcha' => $this->captchaService->generate(),
            'refreshCount' => $this->captchaService->getRefreshCount(),
            'refreshTime' => $this->captchaService->getRefreshTime(),
        ]);
    }
}
