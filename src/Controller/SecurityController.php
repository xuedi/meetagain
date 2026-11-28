<?php declare(strict_types=1);

namespace App\Controller;

use App\Activity\ActivityService;
use App\Activity\Messages\Registered;
use App\Activity\Messages\RegistrationEmailConfirmed;
use App\Emails\Types\VerificationRequestEmail;
use App\Emails\Types\WelcomeEmail;
use App\Entity\Session\Consent;
use App\Entity\User;
use App\EntityActionDispatcher;
use App\Enum\ConsentType;
use App\Enum\EntityAction;
use App\Enum\SecurityEventType;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\EventSubscriber\KernelRequestSubscriber;
use App\EventSubscriber\Security\LoginAttemptSubscriber;
use App\Form\NewPasswordType;
use App\Form\PasswordResetType;
use App\Form\RegistrationType;
use App\Service\Config\ConfigService;
use App\Service\Member\ConsentService;
use App\Service\Member\PasswordResetService;
use App\Service\Member\RegistrationApprovalProviderInterface;
use App\Service\Member\UserService;
use App\Service\Security\LoginGuard;
use App\Service\Security\SecurityService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Email\Contract\BlocklistInterface;
use SensitiveParameter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SecurityController extends AbstractController
{
    use TargetPathTrait;

    public const string LOGIN_ROUTE = 'app_login';
    private const string FIREWALL = 'main';
    private const int USER_AGENT_MAX = 512;

    /** @param iterable<RegistrationApprovalProviderInterface> $registrationApprovalProviders */
    public function __construct(
        private readonly ActivityService $activityService,
        private readonly VerificationRequestEmail $verificationRequestEmail,
        private readonly WelcomeEmail $welcomeEmail,
        private readonly AuthenticationUtils $authenticationUtils,
        private readonly Security $security,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ConsentService $consentService,
        private readonly PasswordResetService $passwordResetService,
        private readonly EntityActionDispatcher $entityActionDispatcher,
        private readonly ConfigService $configService,
        private readonly BlocklistInterface $blocklist,
        private readonly TranslatorInterface $translator,
        #[Target('passwordReset')]
        private readonly RateLimiterFactoryInterface $passwordResetLimiter,
        #[Target('registration')]
        private readonly RateLimiterFactoryInterface $registrationLimiter,
        private readonly SecurityService $securityService,
        private readonly LoginGuard $loginGuard,
        private readonly LockFactory $lockFactory,
        private readonly UserService $userService,
        #[AutowireIterator(RegistrationApprovalProviderInterface::class)]
        private readonly iterable $registrationApprovalProviders,
        private readonly KernelRequestSubscriber $jumpBack,
    ) {}

    #[Route(path: '/login', name: self::LOGIN_ROUTE, defaults: [LoginAttemptSubscriber::ROUTE_DEFAULT => true])]
    public function login(Request $request): Response
    {
        $error = $this->authenticationUtils->getLastAuthenticationError();
        $lastUsername = $this->authenticationUtils->getLastUsername();

        $target = $this->loginTarget($request);

        return $this->render('security/login.html.twig', [
            'redirectPath' => $target ?? $this->generateUrl('app_profile'),
            'registerRedirect' => $target,
            'last_username' => $lastUsername,
            'error' => $error,
            'badCredentials' => $error instanceof BadCredentialsException,
            'meta' => $this->loginGuard->isActive($request) ? $this->loginGuard->createMeasuresForm()->createView() : null,
        ]);
    }

    #[Route(path: '/security/blocked', name: 'app_security_blocked')]
    public function blocked(): Response
    {
        return $this->render('security/blocked.html.twig', [], new Response('', 403));
    }

    #[Route(path: '/logout', name: 'app_security_logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('logout', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $osmConsent = $this->getAuthedUser()->isOsmConsent();

        $this->security->logout(false);

        $consent = Consent::createByCookies($request->cookies);
        $consent->setCookies(ConsentType::Granted);
        $consent->save($request->getSession());

        $response = $this->redirectToRoute('app_login');
        $this->consentService->setShowOsm($osmConsent, $response);

        return $response;
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, EntityManagerInterface $em): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$this->registrationLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            $submittedEmail = $request->request->all('registration')['email'] ?? null;
            $context = ['limiter' => 'registration'];
            if (is_string($submittedEmail) && $submittedEmail !== '') {
                $context['userIdentifier'] = $submittedEmail;
            }
            $this->securityService->event(SecurityEventType::RateLimit, $request, $context);
            return $this->render(
                'rate_limited.html.twig',
                [
                    'message' => $this->translator->trans('security.rate_limited_register_message'),
                ],
                new Response('', 429),
            );
        }

        $redirect = $this->sameHostPath($request->query->get('redirect'));
        if (!$form->isSubmitted()) {
            $redirect ??= $this->refererTarget($request);
        } elseif ($redirect !== null) {
            $request->getSession()->set('redirectUrl', $redirect);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $blockReason = $this->blocklist->reasonFor((string) $user->getEmail());
            if ($blockReason !== null) {
                return $this->render('security/register_blocked.html.twig', [
                    'reason' => $blockReason,
                    'supportPath' => $this->generateUrl('app_contact'),
                ]);
            }

            $lock = $this->lockFactory->createLock('register.' . hash('xxh3', mb_strtolower((string) $user->getEmail())));
            $lock->acquire(true);
            try {
                if ($em->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]) instanceof User) {
                    $form->get('email')->addError(new FormError($this->translator->trans('security.validator_email_unique', [], 'validators')));

                    return $this->render('security/register.html.twig', ['form' => $form, 'emailTaken' => true, 'redirect' => $redirect]);
                }

                $this->persistNewRegistration($em, $request, $user, $form->get('plainPassword')->getData());
            } finally {
                $lock->release();
            }

            $this->entityActionDispatcher->dispatch(EntityAction::CreateUser, $user->getId());

            $this->activityService->log(Registered::TYPE, $user, $this->requestMeta($request));
            $this->verificationRequestEmail->send(['user' => $user]);

            return $this->render('security/register_email_send.html.twig');
        }

        return $this->render('security/register.html.twig', ['form' => $form, 'redirect' => $redirect]);
    }

    #[Route('/register/verify/{code}', name: 'app_register_confirm_email')]
    public function verifyUserEmail(EntityManagerInterface $em, string $code): Response
    {
        $user = $em->getRepository(User::class)->findOneBy(['regcode' => $code]);
        if (
            $user === null
            || $user->getStatus() !== UserStatus::Registered
            || $user->isRegcodeExpired()
            || !$this->userService->claimRegistrationCode($user, $code)
        ) {
            return $this->render('security/register_error.html.twig');
        }

        $this->activityService->log(RegistrationEmailConfirmed::TYPE, $user, []);

        if (!$this->requiresApproval($user)) {
            $user->setStatus(UserStatus::Active);
            $em->persist($user);
            $em->flush();

            $this->welcomeEmail->send(['user' => $user]);

            return $this->render('security/approved.html.twig');
        }

        $user->setStatus(UserStatus::EmailVerified);
        $em->persist($user);
        $em->flush();

        return $this->render('security/register_success.html.twig');
    }

    private function requiresApproval(User $user): bool
    {
        foreach ($this->registrationApprovalProviders as $provider) {
            $answer = $provider->requiresApproval($user);
            if ($answer !== null) {
                return $answer;
            }
        }

        return !$this->configService->isAutomaticRegistration();
    }

    #[Route(path: '/reset', name: 'app_reset')]
    public function reset(Request $request): Response
    {
        $form = $this->createForm(PasswordResetType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() && $this->authenticationUtils->getLastUsername() !== '') {
            $form->get('email')->setData($this->authenticationUtils->getLastUsername());
        }

        if ($form->isSubmitted() && !$this->passwordResetLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            $submittedEmail = $request->request->all('password_reset')['email'] ?? null;
            $context = ['limiter' => 'password_reset'];
            if (is_string($submittedEmail) && $submittedEmail !== '') {
                $context['userIdentifier'] = $submittedEmail;
            }
            $this->securityService->event(SecurityEventType::RateLimit, $request, $context);
            return $this->render(
                'rate_limited.html.twig',
                [
                    'message' => $this->translator->trans('security.rate_limited_reset_message'),
                ],
                new Response('', 429),
            );
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();

            $blockReason = $this->blocklist->reasonFor($email);
            if ($blockReason !== null) {
                return $this->render('security/reset_blocked.html.twig', [
                    'reason' => $blockReason,
                    'supportPath' => $this->generateUrl('app_contact'),
                ]);
            }

            $this->passwordResetService->requestReset($email);

            return $this->render('security/reset_email_send.html.twig');
        }

        return $this->render('security/reset.html.twig', ['form' => $form]);
    }

    #[Route('/reset/verify/{code}', name: 'app_reset_password')]
    public function resetPassword(string $code, Request $request): Response
    {
        $user = $this->passwordResetService->findUserByResetCode($code);
        if (!$user instanceof User) {
            return $this->render('security/reset_error.html.twig');
        }

        $form = $this->createForm(NewPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->passwordResetService->claimResetCode($user, $code)) {
                return $this->render('security/reset_error.html.twig');
            }

            $finalized = $this->passwordResetService->resetPassword($user, $form->get('password')->getData());
            if (!$finalized) {
                return $this->render('security/reset_blocked.html.twig', [
                    'reason' => $this->blocklist->reasonFor((string) $user->getEmail()) ?? '',
                    'supportPath' => $this->generateUrl('app_contact'),
                ]);
            }

            return $this->render('security/reset_success.html.twig');
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form,
        ]);
    }

    private function persistNewRegistration(EntityManagerInterface $em, Request $request, User $user, #[SensitiveParameter] string $plainPassword): void
    {
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
        $user->setRole(UserRole::User);
        $user->setNotification(true);
        $user->setStatus(UserStatus::Registered);
        $user->setPublic(true);
        $user->setVerified(false);
        $user->setLocale($request->getLocale());
        $user->setRegcode(bin2hex(random_bytes(32)));
        $user->setRegcodeExpiresAt(new DateTimeImmutable(User::CONFIRMATION_LINK_LIFETIME));
        $user->setLastLogin(new DateTimeImmutable());
        $user->setCreatedAt(new DateTimeImmutable());
        $user->setBio(null);
        $user->setOsmConsent($this->consentService->getShowOsm());

        $em->persist($user);
        $em->flush();
    }

    /**
     * @return array<string, string>
     */
    private function requestMeta(Request $request): array
    {
        return [
            'ip' => $request->getClientIp() ?? '',
            'user_agent' => mb_substr((string) $request->headers->get('User-Agent'), 0, self::USER_AGENT_MAX),
        ];
    }

    private function loginTarget(Request $request): ?string
    {
        return $this->sameHostPath($request->query->get('redirect')) ?? $this->sessionTarget($request) ?? $this->refererTarget($request);
    }

    private function sessionTarget(Request $request): ?string
    {
        if (!$request->hasPreviousSession()) {
            return null;
        }

        $session = $request->getSession();
        $stored = $this->getTargetPath($session, self::FIREWALL);
        if ($stored !== null) {
            $this->removeTargetPath($session, self::FIREWALL);
        }

        return $this->sameHostPath($session->get('redirectUrl')) ?? $this->sameHostPath($this->localPart($request, $stored));
    }

    private function refererTarget(Request $request): ?string
    {
        $referer = $request->headers->get('Referer');
        if ($referer === null || !str_starts_with($referer, $request->getSchemeAndHttpHost() . '/')) {
            return null;
        }

        $path = $this->sameHostPath($this->localPart($request, $referer));

        return $path !== null && $this->jumpBack->isJumpBackPath($path) ? $path : null;
    }

    private function localPart(Request $request, ?string $url): ?string
    {
        $origin = $request->getSchemeAndHttpHost();
        if ($url === null || !str_starts_with($url, $origin . '/')) {
            return $url;
        }

        return substr($url, strlen($origin));
    }

    private function sameHostPath(mixed $path): ?string
    {
        if (!is_string($path) || !str_starts_with($path, '/') || str_starts_with($path, '//') || strlen($path) > 2000) {
            return null;
        }

        return preg_match('/[\x00-\x1f\x7f\\\\]/', $path) === 1 ? null : $path;
    }
}
