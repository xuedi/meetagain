<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Entity\ActivityType;
use App\Entity\UserStatus;
use App\Form\RegistrationType;
use App\Repository\UserRepository;
use App\Service\ActivityService;
use App\Service\EmailService;
use App\Service\GlobalService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        GlobalService $globalService,
        EmailService $emailService,
        ActivityService $activityService,
    ): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword($hasher->hashPassword($user, $plainPassword));
            $user->setRoles(['ROLE_USER']);
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

            $activityService->log(ActivityType::Registered, $user, []);

            $emailService->sendEmailConformationRequest($user, $request);

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
        if($xuedi !== null) {
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

        return $this->render('security/register_success.html.twig');
    }

    #[Route(path: '/reset', name: 'app_reset')]
    public function reset(AuthenticationUtils $authenticationUtils): Response
    {
        //$error = $authenticationUtils->getLastAuthenticationError();
        //$lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/reset.html.twig', [
            //'last_username' => $lastUsername,
            //'error' => $error,
            'error' => null,
        ]);
    }
}
