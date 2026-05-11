<?php declare(strict_types=1);

namespace App\Controller\Admin\Security;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Security\Permission\Attribute\PermissionAttribute;
use App\Service\Security\BlockedSessionStore;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/security/blocked')]
final class BlockedSessionsController extends AbstractSecurityController implements AdminNavigationInterface, AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly BlockedSessionStore $blockStore,
    ) {
        parent::__construct($translator, 'blocked');
    }

    #[Route('', name: 'app_admin_security_blocked')]
    public function list(): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SECURITY_INCIDENTS_READ);

        $blockedSessions = $this->blockStore->listBlockedSessions();
        $blockedIps = $this->blockStore->listBlockedIps();

        $adminTop = new AdminTop(
            info: [
                new AdminTopInfoHtml(sprintf(
                    '<strong>%d</strong>&nbsp;%s',
                    count($blockedSessions),
                    $this->translator->trans('admin_security.summary_blocked_sessions'),
                )),
                new AdminTopInfoHtml(sprintf(
                    '<strong>%d</strong>&nbsp;%s',
                    count($blockedIps),
                    $this->translator->trans('admin_security.summary_blocked_ips'),
                )),
            ],
            actions: [],
        );

        return $this->render('admin/security/blocked_sessions.html.twig', [
            'active' => 'security',
            'blockedSessions' => $blockedSessions,
            'blockedIps' => $blockedIps,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/session/unblock', name: 'app_admin_security_unblock_session', methods: ['POST'])]
    public function unblockSession(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SECURITY_INCIDENTS_READ);

        $sessionId = $request->request->getString('sessionId');
        if ($sessionId !== '') {
            $this->blockStore->unblockSession($sessionId);
        }

        return $this->redirectToRoute('app_admin_security_blocked');
    }

    #[Route('/ip/unblock', name: 'app_admin_security_unblock_ip', methods: ['POST'])]
    public function unblockIp(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SECURITY_INCIDENTS_READ);

        $ip = $request->request->getString('ip');
        if ($ip !== '') {
            $this->blockStore->unblockIp($ip);
        }

        return $this->redirectToRoute('app_admin_security_blocked');
    }
}
