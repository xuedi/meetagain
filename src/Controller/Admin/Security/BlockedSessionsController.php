<?php declare(strict_types=1);

namespace App\Controller\Admin\Security;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionForm;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Security\Permission\Attribute\PermissionAttribute;
use App\Service\Security\BlockedSessionStore;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/security/blocked')]
final class BlockedSessionsController extends AbstractSecurityController implements
    AdminNavigationInterface,
    AdminTabsInterface
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

        $actions = [];
        if (count($entries) > 0) {
            $actions[] = new AdminTopActionForm(
                label: $this->translator->trans('global.button_clear'),
                target: $this->generateUrl('app_admin_security_blocked_clear'),
                csrfTokenId: 'admin_security_blocked_clear',
                icon: 'trash',
            );
        }

        $adminTop = new AdminTop(info: [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                count($entries),
                $this->translator->trans('admin_security.summary_blocked_total'),
            )),
        ], actions: $actions);

        return $this->render('admin/security/blocked_sessions.html.twig', [
            'active' => 'security',
            'entries' => $entries,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/clear', name: 'app_admin_security_blocked_clear', methods: ['POST'])]
    public function clear(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SECURITY_INCIDENTS_READ);

        if (!$this->isCsrfTokenValid('admin_security_blocked_clear', (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->blockStore->clearAll();

        return $this->redirectToRoute('app_admin_security_blocked');
    }
}
