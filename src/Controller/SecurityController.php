<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\ActivityType;
use App\Entity\Message;
use App\Entity\Session\Consent;
use App\Entity\Session\ConsentType;
use App\Entity\User;
use App\Entity\UserStatus;
use App\Form\NewPasswordType;
use App\Form\PasswordResetType;
use App\Form\RegistrationType;
use App\Service\ActivityService;
use App\Service\CaptchaService;
use App\Service\ConsentService;
use App\Service\EmailService;
use App\Service\PasswordResetService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public const string LOGIN_ROUTE = 'app_login';

    public function __construct(
        private readonly ActivityService $activityService,
        private readonly EmailService $emailService,
        private readonly AuthenticationUtils $authenticationUtils,
        private readonly Security $security,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ConsentService $consentService,
        private readonly CaptchaService $captchaService,
        private readonly PasswordResetService $passwordResetService,
    ) {
    }

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

    #[Route(path: '/logout', name: 'app_security_logout')]
    public function logout(Request $request): Response
    {
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
    public function register(
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
            $user->setRoles(['ROLE_USER']);
            $user->setNotification(true);
            $user->setStatus(UserStatus::Registered);
            $user->setPublic(true);
            $user->setVerified(false);
            $user->setLocale($request->getLocale());
            $user->setRegcode(sha1(random_bytes(128)));
            $user->setLastLogin(new DateTime());
            $user->setCreatedAt(new DateTimeImmutable());
            $user->setBio(null);
            $user->setOsmConsent($this->consentService->getShowOsm());

            $em->persist($user);
            $em->flush();

            $this->activityService->log(ActivityType::Registered, $user, []);
            $this->emailService->prepareVerificationRequest($user);
            $this->emailService->sendQueue(); // TODO: use cron instead

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
        if ($user === null) {
            return $this->render('security/register_error.html.twig');
        }
        $user->setStatus(UserStatus::EmailVerified);
        $user->setRegcode(null);

        $em->persist($user);
        $em->flush();

        $xuedi = $em->getRepository(User::class)->findOneBy(['email' => 'admin@beijingcode.org']);
        if ($xuedi !== null) {
            $msg = new Message();
            $msg->setDeleted(false);
            $msg->setWasRead(false);
            $msg->setSender($xuedi);
            $msg->setReceiver($user);
            $msg->setCreatedAt(new DateTimeImmutable());
            $msg->setContent('Welcome to the community! Feel free to ask me anything. Or suggest a new features.');

            $em->persist($msg);
            $em->flush();
        }
        $this->activityService->log(ActivityType::RegistrationEmailConfirmed, $user, []);

        return $this->render('security/register_success.html.twig');
    }

    #[Route(path: '/reset', name: 'app_reset')]
    public function reset(Request $request): Response
    {
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
                $user = $this->passwordResetService->requestReset($email);
                if (!$user instanceof User) {
                    $form->get('email')->addError(new FormError('No valid user found'));
                } else {
                    return $this->render('security/reset_email_send.html.twig');
                }
            }

            $this->captchaService->reset();
        } else {
            $this->captchaService->reset();
        }

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
            $this->passwordResetService->resetPassword($user, $form->get('password')->getData());

            return $this->render('security/reset_success.html.twig');
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form,
        ]);
    }
}
