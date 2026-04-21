<?php declare(strict_types=1);

namespace App\Controller;

use App\Activity\ActivityService;
use App\Activity\Messages\Registered;
use App\Activity\Messages\RegistrationEmailConfirmed;
use App\Entity\Session\Consent;
use App\Entity\User;
use App\EntityActionDispatcher;
use App\Enum\ConsentType;
use App\Enum\EntityAction;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Form\NewPasswordType;
use App\Form\PasswordResetType;
use App\Form\RegistrationType;
use App\Emails\Types\VerificationRequestEmail;
use App\Emails\Types\WelcomeEmail;
use App\Repository\EmailBlocklistRepository;
use App\Service\Config\ConfigService;
use App\Service\Member\CaptchaService;
use App\Service\Member\ConsentService;
use App\Service\Member\PasswordResetService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
final class SecurityController extends AbstractController
{
    public const string LOGIN_ROUTE = 'app_login';

    public function __construct(
        private readonly ActivityService $activityService,
        private readonly VerificationRequestEmail $verificationRequestEmail,
        private readonly WelcomeEmail $welcomeEmail,
        private readonly AuthenticationUtils $authenticationUtils,
        private readonly Security $security,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ConsentService $consentService,
        private readonly CaptchaService $captchaService,
        private readonly PasswordResetService $passwordResetService,
        private readonly EntityActionDispatcher $entityActionDispatcher,
        private readonly ConfigService $configService,
        private readonly EmailBlocklistRepository $emailBlocklistRepository,
        #[Target('passwordReset')]
        private readonly RateLimiterFactory $passwordResetLimiter,
        #[Target('registration')]
        private readonly RateLimiterFactory $registrationLimiter,
    ) {}

    #[Route(path: '/login', name: self::LOGIN_ROUTE)]
    public function login(Request $request): Response
    {
        $error = $this->authenticationUtils->getLastAuthenticationError();
        $lastUsername = $this->authenticationUtils->getLastUsername();

        $redirectPath = $this->generateUrl('app_profile');
        if (null !== $request->getSession()->get('redirectUrl')) {
            $redirectPath = $request->getSession()->get('redirectUrl');
        }

        return $this->render('security/login.html.twig', [
            'redirectPath' => $redirectPath,
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_security_logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('logout', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getAuthedUser();
        $osm = $user->isOsmConsent() ? ConsentType::Granted : ConsentType::Denied;

        $this->security->logout(false);

        $consent = Consent::createByCookies($request->cookies);
        $consent->setCookies(ConsentType::Granted);
        $consent->setOsm($osm);
        $consent->save($request->getSession());

        return $this->redirectToRoute('app_login');
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, EntityManagerInterface $em): Response
    {
        $limiter = $this->registrationLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->render(
                'rate_limited.html.twig',
                [
                    'message' => 'Too many registration attempts. Please try again later.',
                ],
                new Response('', 429),
            );
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
            $user->setRole(UserRole::User);
            $user->setNotification(true);
            $user->setStatus(UserStatus::Registered);
            $user->setPublic(true);
            $user->setVerified(false);
            $user->setLocale($request->getLocale());
            $user->setRegcode(bin2hex(random_bytes(32)));
            $user->setRegcodeExpiresAt(new DateTimeImmutable('+24 hours'));
            $user->setLastLogin(new DateTime());
            $user->setCreatedAt(new DateTimeImmutable());
            $user->setBio(null);
            $user->setOsmConsent($this->consentService->getShowOsm());

            $em->persist($user);
            $em->flush();

            $this->entityActionDispatcher->dispatch(EntityAction::CreateUser, $user->getId());

            $this->activityService->log(Registered::TYPE, $user, []);
            $this->verificationRequestEmail->send(['user' => $user]);

            return $this->render('security/register_email_send.html.twig');
        }

        return $this->render('security/register.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/register/verify/{code}', name: 'app_register_confirm_email')]
    public function verifyUserEmail(EntityManagerInterface $em, string $code): Response
    {
        $user = $em->getRepository(User::class)->findOneBy(['regcode' => $code]);
        if ($user === null || $user->isRegcodeExpired()) {
            return $this->render('security/register_error.html.twig');
        }

        // clean up and write activity
        $user->setRegcode(null);
        $user->setRegcodeExpiresAt(null);
        $this->activityService->log(RegistrationEmailConfirmed::TYPE, $user, []);

        // Check if automatic registration is enabled
        if ($this->configService->isAutomaticRegistration()) {
            // Auto-approve user
            $user->setStatus(UserStatus::Active);
            $em->persist($user);
            $em->flush();

            // Send welcome email
            $this->welcomeEmail->send(['user' => $user]);

            // Redirect to events page
            return $this->render('security/approved.html.twig');
        }

        // Manual approval required
        $user->setStatus(UserStatus::EmailVerified);
        $em->persist($user);
        $em->flush();

        return $this->render('security/register_success.html.twig');
    }

    #[Route(path: '/reset', name: 'app_reset')]
    public function reset(Request $request): Response
    {
        $limiter = $this->passwordResetLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->render(
                'rate_limited.html.twig',
                [
                    'message' => 'Too many password reset attempts. Please try again later.',
                ],
                new Response('', 429),
            );
        }

        $form = $this->createForm(PasswordResetType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $captcha = $form->get('captcha')->getData();
            $captchaError = $this->captchaService->isValid($captcha);
            if ($captchaError !== null) {
                $form->get('captcha')->addError(new FormError($captchaError));
            }

            $email = $form->get('email')->getData();

            if ($form->getErrors(true)->count() === 0) {
                $blocklistEntry = $this->emailBlocklistRepository->findByEmail($email);
                if ($blocklistEntry !== null) {
                    return $this->render('security/reset_blocked.html.twig', [
                        'reason' => $blocklistEntry->getReason(),
                        'supportPath' => $this->generateUrl('app_contact'),
                    ]);
                }

                $this->passwordResetService->requestReset($email);

                return $this->render('security/reset_email_send.html.twig');
            }

        }

        $this->captchaService->reset();
        return $this->render('security/reset.html.twig', [
            'captcha' => $this->captchaService->generate(),
            'refreshCount' => $this->captchaService->getRefreshCount(),
            'refreshTime' => $this->captchaService->getRefreshTime(),
            'form' => $form,
        ]);
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
            $finalized = $this->passwordResetService->resetPassword($user, $form->get('password')->getData());
            if (!$finalized) {
                $blocklistEntry = $this->emailBlocklistRepository->findByEmail((string) $user->getEmail());

                return $this->render('security/reset_blocked.html.twig', [
                    'reason' => $blocklistEntry?->getReason() ?? '',
                    'supportPath' => $this->generateUrl('app_contact'),
                ]);
            }

            return $this->render('security/reset_success.html.twig');
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form,
        ]);
    }
}
