<?php declare(strict_types=1);

namespace App\Controller\Admin\Security;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Controller\Profile\DeveloperAppsController as ProfileDeveloperAppsController;
use App\Entity\DeveloperAppApplication;
use App\Enum\DeveloperAppStatus;
use App\Repository\DeveloperAppApplicationRepository;
use App\Security\Permission\Attribute\PermissionAttribute;
use App\Service\Security\DeveloperAppApprovalService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/security/developer-apps')]
final class DeveloperAppsController extends AbstractSecurityController implements AdminNavigationInterface, AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly DeveloperAppApplicationRepository $repo,
        private readonly DeveloperAppApprovalService $approvalService,
    ) {
        parent::__construct($translator, 'developer_apps');
    }

    #[Route('', name: 'app_admin_security_developer_apps')]
    public function list(Request $request): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::DEVELOPER_APP_REVIEW);

        $statusParam = $request->query->getString('status', 'pending');
        $status = $this->resolveStatus($statusParam);

        if ($status === null) {
            $apps = $this->repo->findRecent(200);
        } else {
            $apps = $this->repo->findByStatus($status, 200, 0);
        }

        $pendingCount = $this->repo->countByStatus(DeveloperAppStatus::Pending);
        $totalCount = $this->repo->countByStatus(DeveloperAppStatus::Pending)
            + $this->repo->countByStatus(DeveloperAppStatus::Approved)
            + $this->repo->countByStatus(DeveloperAppStatus::Denied)
            + $this->repo->countByStatus(DeveloperAppStatus::Revoked);

        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $totalCount,
                $this->translator->trans('admin_security.summary_total_developer_apps'),
            )),
            new AdminTopInfoHtml(sprintf(
                '<span class="tag %s is-medium">%d %s</span>',
                $pendingCount > 0 ? 'is-warning' : 'is-light',
                $pendingCount,
                $this->translator->trans('admin_security.summary_pending_apps'),
            )),
        ];

        $adminTop = new AdminTop(info: $info, actions: []);

        return $this->render('admin/security/developer_apps_list.html.twig', [
            'active' => 'security',
            'apps' => $apps,
            'currentStatus' => $statusParam,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_security_developer_apps_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::DEVELOPER_APP_REVIEW);

        $app = $this->repo->find($id);
        if (!$app instanceof DeveloperAppApplication) {
            throw new NotFoundHttpException();
        }

        $otherApps = array_filter(
            $this->repo->findRecentByUser($app->getSubmittedBy()),
            static fn(DeveloperAppApplication $other): bool => $other->getId() !== $app->getId(),
        );

        $adminTop = new AdminTop(
            info: [
                new AdminTopInfoHtml(sprintf(
                    '<strong>%s</strong>',
                    htmlspecialchars($app->getAppName(), ENT_QUOTES),
                )),
                new AdminTopInfoHtml(sprintf(
                    '<span class="tag %s is-medium">%s</span>',
                    $app->getStatus()->tagClass(),
                    $this->translator->trans($app->getStatus()->label()),
                )),
            ],
            actions: [],
        );

        return $this->render('admin/security/developer_apps_show.html.twig', [
            'active' => 'security',
            'app' => $app,
            'otherApps' => array_values($otherApps),
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/{id}/approve', name: 'app_admin_security_developer_apps_approve', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function approve(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::DEVELOPER_APP_REVIEW);

        $app = $this->repo->find($id);
        if (!$app instanceof DeveloperAppApplication) {
            throw new NotFoundHttpException();
        }

        if (!$this->isCsrfTokenValid('developer_apps_approve_' . $app->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'global.flash_invalid_csrf');

            return $this->redirectToRoute('app_admin_security_developer_apps_show', ['id' => $app->getId()]);
        }

        if ($app->getStatus() !== DeveloperAppStatus::Pending && $app->getStatus() !== DeveloperAppStatus::Denied) {
            $this->addFlash('error', 'admin_security.flash_developer_apps_invalid_state');

            return $this->redirectToRoute('app_admin_security_developer_apps_show', ['id' => $app->getId()]);
        }

        $secret = $this->approvalService->approve($app, $this->getUser());

        $session = $request->getSession();
        if ($session instanceof SessionInterface) {
            $session->set(ProfileDeveloperAppsController::SECRET_FLASH_KEY, [
                'id' => $app->getId(),
                'secret' => $secret,
            ]);
        }

        $this->addFlash('success', 'admin_security.flash_developer_apps_approved');

        return $this->redirectToRoute('app_profile_developer_apps_show', ['id' => $app->getId()]);
    }

    #[Route('/{id}/deny', name: 'app_admin_security_developer_apps_deny', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deny(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::DEVELOPER_APP_REVIEW);

        $app = $this->repo->find($id);
        if (!$app instanceof DeveloperAppApplication) {
            throw new NotFoundHttpException();
        }

        if (!$this->isCsrfTokenValid('developer_apps_deny_' . $app->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'global.flash_invalid_csrf');

            return $this->redirectToRoute('app_admin_security_developer_apps_show', ['id' => $app->getId()]);
        }

        $reason = trim((string) $request->request->get('reason', ''));
        $this->approvalService->deny($app, $this->getUser(), $reason);

        $this->addFlash('success', 'admin_security.flash_developer_apps_denied');

        return $this->redirectToRoute('app_admin_security_developer_apps_show', ['id' => $app->getId()]);
    }

    #[Route('/{id}/revoke', name: 'app_admin_security_developer_apps_revoke', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function revoke(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::DEVELOPER_APP_REVOKE);

        $app = $this->repo->find($id);
        if (!$app instanceof DeveloperAppApplication) {
            throw new NotFoundHttpException();
        }

        if (!$this->isCsrfTokenValid('developer_apps_revoke_' . $app->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'global.flash_invalid_csrf');

            return $this->redirectToRoute('app_admin_security_developer_apps_show', ['id' => $app->getId()]);
        }

        $reason = trim((string) $request->request->get('reason', ''));
        $this->approvalService->revoke($app, $this->getUser(), $reason);

        $this->addFlash('success', 'admin_security.flash_developer_apps_revoked');

        return $this->redirectToRoute('app_admin_security_developer_apps_show', ['id' => $app->getId()]);
    }

    private function resolveStatus(string $param): ?DeveloperAppStatus
    {
        return match ($param) {
            'pending' => DeveloperAppStatus::Pending,
            'approved' => DeveloperAppStatus::Approved,
            'denied' => DeveloperAppStatus::Denied,
            'revoked' => DeveloperAppStatus::Revoked,
            'all' => null,
            default => DeveloperAppStatus::Pending,
        };
    }
}
