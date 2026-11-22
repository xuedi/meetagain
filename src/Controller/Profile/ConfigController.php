<?php declare(strict_types=1);

namespace App\Controller\Profile;

use App\Controller\AbstractController;
use App\Emails\Types\AccountDeletionEmail;
use App\Entity\NotificationSettings;
use App\Form\ChangePassword;
use App\Security\Permission\Attribute\PermissionAttribute;
use App\Service\Member\ActionException;
use App\Service\Member\ActionFailure;
use App\Service\Member\ConsentService;
use App\Service\Member\DataExport;
use App\Service\Member\Erasure;
use App\Service\Member\PasswordResetService;
use App\Service\Member\UserService;
use App\Service\Notification\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/profile/config')]
#[IsGranted('ROLE_USER')]
final class ConfigController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ConsentService $consentService,
        private readonly SettingsService $notificationSettings,
        private readonly PasswordResetService $passwordService,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UserService $userService,
        private readonly DataExport $dataExport,
        private readonly AccountDeletionEmail $accountDeletionEmail,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('', name: 'app_profile_config', methods: ['GET'])]
    public function config(): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::USER_VIEW_SELF);

        return $this->render('profile/config/settings.html.twig', [
            'user' => $this->getAuthedUser(),
        ]);
    }

    #[Route('/password', name: 'app_profile_config_password', methods: ['GET', 'POST'])]
    public function password(Request $request): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::USER_VIEW_SELF);

        $form = $this->createForm(ChangePassword::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccessUnlessGranted(PermissionAttribute::USER_PASSWORD_UPDATE);
            $user = $this->getAuthedUser();
            if (!$this->hasher->isPasswordValid($user, $form->get('oldPassword')->getData())) {
                $this->addFlash('error', 'profile_config.flash_password_old_invalid');

                return $this->redirectToRoute('app_profile_config_password');
            }
            $this->passwordService->changePassword($user, $form->get('newPassword')->getData());
            $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
            $this->addFlash('success', 'profile_config.flash_password_changed');

            return $this->redirectToRoute('app_profile_config_password');
        }

        return $this->render('profile/config/password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/export', name: 'app_profile_config_export_page', methods: ['GET'])]
    public function exportPage(): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::USER_VIEW_SELF);

        return $this->render('profile/config/export.html.twig', [
            'user' => $this->getAuthedUser(),
        ]);
    }

    #[Route('/delete', name: 'app_profile_config_delete_page', methods: ['GET'])]
    public function deletePage(): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::USER_VIEW_SELF);
        $user = $this->getAuthedUser();

        return $this->render('profile/config/delete.html.twig', [
            'user' => $user,
            'deletionBlocker' => $this->userService->findDeletionBlocker($user),
            'erasureWindowDays' => Erasure::ERASURE_WINDOW_DAYS,
        ]);
    }

    #[Route('/delete', name: 'app_profile_config_delete', methods: ['POST'])]
    public function delete(Request $request): Response
    {
        $user = $this->getAuthedUser();
        if (!$this->isCsrfTokenValid('app_profile_config_delete' . $user->getId(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
        $this->denyAccessUnlessGranted(PermissionAttribute::USER_UPDATE_SELF);

        if (!$this->hasher->isPasswordValid($user, (string) $request->request->get('password'))) {
            $this->addFlash('error', 'profile_config.flash_password_invalid');

            return $this->redirectToRoute('app_profile_config_delete_page');
        }

        $typed = mb_strtolower(trim((string) $request->request->get('confirmation')));
        if ($typed !== mb_strtolower($this->translator->trans('profile_config.delete_confirm_word'))) {
            $this->addFlash('error', 'profile_config.flash_delete_confirmation_invalid');

            return $this->redirectToRoute('app_profile_config_delete_page');
        }

        try {
            $this->userService->requestOwnDeletion($user);
        } catch (ActionException $e) {
            $this->addFlash('error', $e->failure === ActionFailure::DeletionBlocked ? $e->getMessage() : 'profile_config.flash_delete_failed');

            return $this->redirectToRoute('app_profile_config_delete_page');
        }

        $this->accountDeletionEmail->send(['user' => $user]);
        $this->security->logout(false);
        $this->addFlash('success', 'profile_config.flash_deleted');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/export', name: 'app_profile_config_export', methods: ['POST'])]
    public function export(Request $request): Response
    {
        $user = $this->getAuthedUser();
        if (!$this->isCsrfTokenValid('app_profile_config_export' . $user->getId(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
        $this->denyAccessUnlessGranted(PermissionAttribute::USER_VIEW_SELF);

        if (!$this->hasher->isPasswordValid($user, (string) $request->request->get('password'))) {
            $this->addFlash('error', 'profile_config.flash_password_invalid');

            return $this->redirectToRoute('app_profile_config_export_page');
        }

        $response = new BinaryFileResponse($this->dataExport->build($user));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'my-data.zip');
        $response->headers->set('Content-Type', 'application/zip');
        $response->deleteFileAfterSend();

        return $response;
    }

    #[Route(
        '/toggle/{type}',
        name: 'app_profile_config_toggle',
        requirements: [
            'type' => 'osm|tagging|notification|public',
        ],
        methods: ['POST'],
    )]
    public function toggle(Request $request, string $type): Response
    {
        if (!$this->isCsrfTokenValid('app_profile_config_toggle' . $type, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->denyAccessUnlessGranted(PermissionAttribute::USER_UPDATE_SELF);

        $user = $this->getAuthedUser();
        $newStatus = match ($type) {
            'osm' => $user->setOsmConsent(!$user->isOsmConsent())->isOsmConsent(),
            'tagging' => $user->setTagging(!$user->isTagging())->isTagging(),
            'notification' => $user->setNotification(!$user->isNotification())->isNotification(),
            'public' => $user->setPublic(!$user->isPublic())->isPublic(),
            default => throw new Exception("Invalid toggle type: {$type}"),
        };
        $this->em->persist($user);
        $this->em->flush();

        $response = $request->isXmlHttpRequest() ? new JsonResponse(['newStatus' => $newStatus]) : $this->redirectToRoute('app_profile_config');

        if ($type === 'osm') {
            $this->consentService->setShowOsm((bool) $newStatus, $response);
        }

        return $response;
    }

    #[Route('/toggleNotification/{type}', name: 'app_profile_config_toggle_notification', methods: ['POST'])]
    public function toggleNotification(Request $request, string $type): Response
    {
        if (!$this->isCsrfTokenValid('app_profile_config_toggle_notification' . $type, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
        if (!NotificationSettings::isKnownKey($type)) {
            throw $this->createNotFoundException();
        }

        $user = $this->getAuthedUser();
        $newStatus = $this->notificationSettings->toggle($user, $type);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['newStatus' => $newStatus]);
        }

        return $this->redirectToRoute('app_profile_config');
    }
}
