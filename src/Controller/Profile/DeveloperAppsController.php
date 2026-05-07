<?php declare(strict_types=1);

namespace App\Controller\Profile;

use App\Controller\AbstractController;
use App\Entity\DeveloperAppApplication;
use App\Enum\DeveloperAppStatus;
use App\Enum\ImageType;
use App\Form\DeveloperAppApplicationType;
use App\Repository\DeveloperAppApplicationRepository;
use App\Security\Permission\Attribute\PermissionAttribute;
use App\Service\Media\ImageService;
use App\Service\Security\DeveloperAppApprovalService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class DeveloperAppsController extends AbstractController
{
    public const string SECRET_FLASH_KEY = 'developer_apps.last_approved_secret';

    public function __construct(
        private readonly DeveloperAppApplicationRepository $repo,
        private readonly EntityManagerInterface $em,
        private readonly ImageService $imageService,
        private readonly DeveloperAppApprovalService $approvalService,
    ) {}

    #[Route('/profile/developer-apps', name: 'app_profile_developer_apps', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $user = $this->getAuthedUser();

        $form = $this->createForm(DeveloperAppApplicationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccessUnlessGranted(PermissionAttribute::DEVELOPER_APP_MANAGE_SELF);

            $data = $form->getData();
            $uris = DeveloperAppApplicationType::parseRedirectUris(
                (string) $form->get(DeveloperAppApplicationType::FIELD_REDIRECT_URIS)->getData(),
            );
            /** @var list<string> $grants */
            $grants = (array) $form->get(DeveloperAppApplicationType::FIELD_GRANTS)->getData();

            $application = new DeveloperAppApplication(
                submittedBy: $user,
                appName: (string) ($data['appName'] ?? ''),
                redirectUris: $uris,
                requestedGrants: $grants,
            );
            $application->setDescription($data['description'] ?? null);
            $application->setHomepageUrl($data['homepageUrl'] ?? null);
            $application->setRequestedScopes(['api']);

            $logoUpload = $form->get('logo')->getData();
            if ($logoUpload !== null) {
                $logo = $this->imageService->upload($logoUpload, $user, ImageType::DeveloperAppLogo);
                if ($logo !== null) {
                    $this->em->persist($logo);
                    $this->em->flush();
                    $this->imageService->createThumbnails($logo, ImageType::DeveloperAppLogo);
                    $application->setLogoImage($logo);
                }
            }

            $this->em->persist($application);
            $this->em->flush();

            $this->addFlash('success', 'developer_apps.flash_submitted_pending_review');

            return $this->redirectToRoute('app_profile_developer_apps');
        }

        $apps = $this->repo->findRecentByUser($user);
        $filter = $request->query->getString('filter', '');

        return $this->render('profile/developer_apps/index.html.twig', [
            'form' => $form,
            'apps' => $apps,
            'filter' => $filter,
        ]);
    }

    #[Route('/profile/developer-apps/{id}', name: 'app_profile_developer_apps_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, Request $request): Response
    {
        $user = $this->getAuthedUser();
        $app = $this->repo->findOneByUserAndId($user, $id);
        if ($app === null) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(PermissionAttribute::DEVELOPER_APP_VIEW_SELF, $app);

        $session = $request->getSession();
        $secret = null;
        if ($session instanceof SessionInterface && $session->has(self::SECRET_FLASH_KEY)) {
            $stored = $session->get(self::SECRET_FLASH_KEY);
            if (is_array($stored) && (int) ($stored['id'] ?? 0) === $app->getId()) {
                $secret = (string) ($stored['secret'] ?? '');
            }
        }

        return $this->render('profile/developer_apps/show.html.twig', [
            'app' => $app,
            'clientSecret' => $secret,
        ]);
    }

    #[Route('/profile/developer-apps/{id}/edit', name: 'app_profile_developer_apps_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request): Response
    {
        $user = $this->getAuthedUser();
        $app = $this->repo->findOneByUserAndId($user, $id);
        if ($app === null) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(PermissionAttribute::DEVELOPER_APP_MANAGE_SELF, $app);

        $form = $this->createForm(DeveloperAppApplicationType::class, [
            'appName' => $app->getAppName(),
            'description' => $app->getDescription(),
            'homepageUrl' => $app->getHomepageUrl(),
        ], ['structural_readonly' => true]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $app->setAppName((string) ($data['appName'] ?? $app->getAppName()));
            $app->setDescription($data['description'] ?? null);
            $app->setHomepageUrl($data['homepageUrl'] ?? null);

            $logoUpload = $form->get('logo')->getData();
            if ($logoUpload !== null) {
                $logo = $this->imageService->upload($logoUpload, $user, ImageType::DeveloperAppLogo);
                if ($logo !== null) {
                    $this->em->persist($logo);
                    $this->em->flush();
                    $this->imageService->createThumbnails($logo, ImageType::DeveloperAppLogo);
                    $app->setLogoImage($logo);
                }
            }

            $this->em->flush();
            $this->addFlash('success', 'developer_apps.flash_updated');

            return $this->redirectToRoute('app_profile_developer_apps_show', ['id' => $app->getId()]);
        }

        return $this->render('profile/developer_apps/edit.html.twig', [
            'app' => $app,
            'form' => $form,
        ]);
    }

    #[Route('/profile/developer-apps/{id}/submit-revision', name: 'app_profile_developer_apps_submit_revision', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function submitRevision(int $id, Request $request): Response
    {
        $user = $this->getAuthedUser();
        $app = $this->repo->findOneByUserAndId($user, $id);
        if ($app === null) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(PermissionAttribute::DEVELOPER_APP_MANAGE_SELF, $app);

        $form = $this->createForm(DeveloperAppApplicationType::class, [
            'appName' => $app->getAppName(),
            'description' => $app->getDescription(),
            'homepageUrl' => $app->getHomepageUrl(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $uris = DeveloperAppApplicationType::parseRedirectUris(
                (string) $form->get(DeveloperAppApplicationType::FIELD_REDIRECT_URIS)->getData(),
            );
            /** @var list<string> $grants */
            $grants = (array) $form->get(DeveloperAppApplicationType::FIELD_GRANTS)->getData();

            $app->setAppName((string) ($data['appName'] ?? $app->getAppName()));
            $app->setDescription($data['description'] ?? null);
            $app->setHomepageUrl($data['homepageUrl'] ?? null);
            $app->setRedirectUris($uris);
            $app->setRequestedGrants($grants);
            $app->setStatus(DeveloperAppStatus::Pending);
            $app->setReviewedAt(null);
            $app->setReviewedBy(null);
            $app->setDenyReason(null);
            $app->setUserReadOutcome(true);
            $app->setSubmittedAt(new DateTimeImmutable());

            $this->em->flush();
            $this->addFlash('success', 'developer_apps.flash_resubmitted');

            return $this->redirectToRoute('app_profile_developer_apps_show', ['id' => $app->getId()]);
        }

        return $this->render('profile/developer_apps/submit_revision.html.twig', [
            'app' => $app,
            'form' => $form,
            'currentRedirectUrisRaw' => implode("\n", $app->getRedirectUris()),
        ]);
    }

    #[Route('/profile/developer-apps/{id}/acknowledge', name: 'app_profile_developer_apps_acknowledge', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function acknowledgeOutcome(int $id, Request $request): RedirectResponse
    {
        $user = $this->getAuthedUser();
        $app = $this->repo->findOneByUserAndId($user, $id);
        if ($app === null) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(PermissionAttribute::DEVELOPER_APP_MANAGE_SELF, $app);

        if (!$this->isCsrfTokenValid('developer_apps_acknowledge_' . $app->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'global.flash_invalid_csrf');

            return $this->redirectToRoute('app_profile_developer_apps_show', ['id' => $app->getId()]);
        }

        if (!$app->isUserReadOutcome()) {
            $app->markOutcomeRead();
            $this->em->flush();
        }

        $session = $request->getSession();
        if ($session instanceof SessionInterface && $session->has(self::SECRET_FLASH_KEY)) {
            $stored = $session->get(self::SECRET_FLASH_KEY);
            if (is_array($stored) && (int) ($stored['id'] ?? 0) === $app->getId()) {
                $session->remove(self::SECRET_FLASH_KEY);
            }
        }

        return $this->redirectToRoute('app_profile_developer_apps_show', ['id' => $app->getId()]);
    }

    #[Route('/profile/developer-apps/{id}/delete', name: 'app_profile_developer_apps_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request): RedirectResponse
    {
        $user = $this->getAuthedUser();
        $app = $this->repo->findOneByUserAndId($user, $id);
        if ($app === null) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(PermissionAttribute::DEVELOPER_APP_MANAGE_SELF, $app);

        if (!$this->isCsrfTokenValid('developer_apps_delete_' . $app->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'global.flash_invalid_csrf');

            return $this->redirectToRoute('app_profile_developer_apps_show', ['id' => $app->getId()]);
        }

        $this->approvalService->deleteByOwner($app);

        $this->addFlash('success', 'developer_apps.flash_deleted');

        return $this->redirectToRoute('app_profile_developer_apps');
    }
}
