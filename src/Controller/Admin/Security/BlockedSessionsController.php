<?php declare(strict_types=1);

namespace App\Controller\Admin\Security;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Security\Permission\Attribute\PermissionAttribute;
use App\Service\Security\BlockedSessionStore;
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

        $entries = [];
        foreach ($this->blockStore->listBlockedIps() as $entry) {
            $entries[] = ['type' => 'ip', 'key' => $entry['key'], 'snapshot' => $entry['snapshot']];
        }
        foreach ($this->blockStore->listBlockedSessions() as $entry) {
            $entries[] = ['type' => 'session', 'key' => $entry['key'], 'snapshot' => $entry['snapshot']];
        }

        $adminTop = new AdminTop(
            info: [
                new AdminTopInfoHtml(sprintf(
                    '<strong>%d</strong>&nbsp;%s',
                    count($entries),
                    $this->translator->trans('admin_security.summary_blocked_total'),
                )),
            ],
            actions: [],
        );

        return $this->render('admin/security/blocked_sessions.html.twig', [
            'active' => 'security',
            'entries' => $entries,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

}
