<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\EmailType\ResetPassword;
use App\Entity\EmailType\VerificationRequest;
use App\Entity\Message;
use App\Entity\Session\Consent;
use App\Entity\Session\ConsentType;
use App\Entity\User;
use App\Entity\ActivityType;
use App\Entity\UserStatus;
use App\Form\NewPasswordType;
use App\Form\PasswordResetType;
use App\Form\RegistrationType;
use App\Message\PrepareEmail;
use App\Repository\UserRepository;
use App\Service\ActivityService;
use App\Service\CaptchaService;
use App\Service\GlobalService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    const string LOGIN_ROUTE = 'app_login';

    public function __construct(
        private readonly ActivityService $activityService,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    #[Route(path: '/login', name: self::LOGIN_ROUTE)]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $redirectPath = $this->generateUrl('app_profile');
        if (null !== $request->getSession()->get('redirectUrl', null)) {
            $redirectPath = $request->getSession()->get('redirectUrl');
        }

        return $this->render('security/login.html.twig', [
            'redirectPath' => $redirectPath,
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_security_logout')]
    public function logout(Security $security, Request $request): Response
    {
        $user = $this->getAuthedUser();
        $osm = $user->isOsmConsent() ? ConsentType::Granted : ConsentType::Denied;

        $security->logout(false);

        $consent = Consent::createByCookies($request->cookies);
        $consent->setCookies(ConsentType::Granted);
        $consent->setOsm($osm);
        $consent->save($request->getSession());

        return $this->redirectToRoute('app_login');
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        GlobalService $globalService,
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword($hasher->hashPassword($user, $plainPassword));
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
            $user->setOsmConsent($globalService->getShowOsm());

            $em->persist($user);
            $em->flush();

            $this->activityService->log(ActivityType::Registered, $user, []);
            $this->messageBus->dispatch(PrepareEmail::byType(VerificationRequest::create($user)));

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
            $msg->setContent("Welcome to the community! Feel free to ask me anything. Or suggest a new features.");

            $em->persist($msg);
            $em->flush();
        }
        $this->activityService->log(ActivityType::RegistrationEmailConfirmed, $user, []);

        return $this->render('security/register_success.html.twig');
    }

    #[Route(path: '/reset', name: 'app_reset')]
    public function reset(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo,
        CaptchaService $captchaService,
    ): Response {
        $captchaService->setSession($request->getSession());

        $form = $this->createForm(PasswordResetType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $captcha = $form->get('captcha')->getData();
            $captchaError = $captchaService->isValid($captcha);
            if ($captchaError !== null) {
                $form->get('captcha')->addError(new FormError($captchaError));
            }

            $email = $form->get('email')->getData();
            $user = $userRepo->findOneBy(['email' => $email]);
            if (null === $user) {
                $form->get('email')->addError(new FormError('No valid user found'));
            }

            if ($form->getErrors(true)->count() === 0) {
                $user->setRegcode(sha1(random_bytes(128)));
                $em->persist($user);
                $em->flush();

                $this->activityService->log(ActivityType::PasswordResetRequest, $user);
                $this->messageBus->dispatch(PrepareEmail::byType(ResetPassword::create($user)));

                return $this->render('security/reset_email_send.html.twig');
            } else {
                $captchaService->reset();
            }
        } else {
            $captchaService->reset();
        }

        return $this->render('security/reset.html.twig', [
            'captcha' => $captchaService->generate(),
            'refreshCount' => $captchaService->getRefreshCount(),
            'refreshTime' => $captchaService->getRefreshTime(),
            'form' => $form,
        ]);
    }

    #[Route('/reset/verify/{code}', name: 'app_reset_password')]
    public function resetPassword(
        EntityManagerInterface $em,
        string $code,
        Request $request,
        UserPasswordHasherInterface $hasher,
    ): Response {
        $user = $em->getRepository(User::class)->findOneBy(['regcode' => $code]);
        if ($user === null) {
            return $this->render('security/reset_error.html.twig');
        }

        $form = $this->createForm(NewPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, $form->get('password')->getData()));
            $user->setRegcode(null);

            $em->persist($user);
            $em->flush();

            $this->activityService->log(ActivityType::PasswordReset, $user);

            return $this->render('security/reset_success.html.twig');
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form,
        ]);
    }
}
