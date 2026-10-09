<?php declare(strict_types=1);

namespace App\Controller\Admin\Support;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionForm;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Emails\Types\SupportInvitationEmail;
use App\Entity\SupportRequest;
use App\Entity\User;
use App\Form\SupportReplyType;
use App\Service\Support\ThreadService;
use App\Service\Support\VisibilityResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_STEWARD'), Route('/admin/support')]
final class RequestsController extends AbstractSupportController implements AdminNavigationInterface, AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly SupportInvitationEmail $supportInvitationEmail,
        private readonly ThreadService $threadService,
        private readonly VisibilityResolver $visibilityResolver,
    ) {
        parent::__construct($translator, 'requests');
    }

    #[Route('', name: 'app_admin_support_list')]
    public function list(): Response
    {
        $requests = $this->visibilityResolver->getVisibleRequests();

        $newCount = 0;
        foreach ($requests as $request) {
            if (!$request->isNew()) {
                continue;
            }

            $newCount++;
        }
        $totalCount = count($requests);

        $info = [
            new AdminTopInfoHtml(sprintf('<strong>%d</strong>&nbsp;%s', $totalCount, $this->translator->trans('admin_support.summary_total_requests'))),
        ];
        $info[] = $newCount > 0
            ? new AdminTopInfoHtml(sprintf(
                '<span class="tag is-warning is-medium">%d&nbsp;%s</span>',
                $newCount,
                $this->translator->trans('admin_support.summary_new_requests'),
            ))
            : new AdminTopInfoHtml(sprintf('<span class="tag is-success is-medium">%s</span>', $this->translator->trans('admin_support.summary_all_read')));

        $adminTop = new AdminTop(info: $info);

        return $this->render('admin/support/list.html.twig', [
            'active' => 'support',
            'requests' => $requests,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_support_request_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $request = $this->requireRequest($id);

        $info = [
            new AdminTopInfoHtml(sprintf('<strong>%s</strong>', $request->getCreatedAt()->format('Y-m-d H:i:s'))),
            new AdminTopInfoHtml(sprintf('<span class="tag is-light is-medium">%s</span>', htmlspecialchars(
                $this->translator->trans($request->getAudience()->label()),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            ))),
            new AdminTopInfoHtml(sprintf(
                '<span class="tag %s is-medium">%s</span>',
                $request->getStatus()->tagVariant(),
                htmlspecialchars($this->translator->trans($request->getStatus()->label()), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            )),
        ];

        $actions = [];
        if ($request->isNew()) {
            $actions[] = new AdminTopActionForm(
                label: $this->translator->trans('admin_support.button_mark_read'),
                target: $this->generateUrl('app_admin_support_mark_read', ['id' => $request->getId()]),
                csrfTokenId: 'app_admin_support_mark_read' . $request->getId(),
                icon: 'check',
                variant: 'is-warning',
            );
        }
        $actions[] = $request->isResolved()
            ? new AdminTopActionForm(
                label: $this->translator->trans('admin_support.button_reopen_thread'),
                target: $this->generateUrl('app_admin_support_reopen', ['id' => $request->getId()]),
                csrfTokenId: 'app_admin_support_reopen' . $request->getId(),
                icon: 'rotate-left',
                variant: 'is-warning',
            )
            : new AdminTopActionForm(
                label: $this->translator->trans('admin_support.button_resolve_thread'),
                target: $this->generateUrl('app_admin_support_resolve', ['id' => $request->getId()]),
                csrfTokenId: 'app_admin_support_resolve' . $request->getId(),
                icon: 'circle-check',
                variant: 'is-warning',
            );
        if ($request->canInviteAdmins() && !$this->isGranted('ROLE_ADMIN')) {
            $actions[] = new AdminTopActionForm(
                label: $this->translator->trans('admin_support.button_invite_admins'),
                target: $this->generateUrl('app_admin_support_invite_admins', ['id' => $request->getId()]),
                csrfTokenId: 'app_admin_support_invite_admins' . $request->getId(),
                icon: 'user-plus',
                variant: 'is-warning',
                confirm: $this->translator->trans('admin_support.warning_invite_admins_confirm'),
            );
        }
        $actions[] = new AdminTopActionButton(
            label: $this->translator->trans('admin_support.button_back'),
            target: $this->generateUrl('app_admin_support_list'),
            icon: 'arrow-left',
        );

        $adminTop = new AdminTop(info: $info, actions: $actions);

        return $this->render('admin/support/request_show.html.twig', [
            'active' => 'support',
            'request' => $request,
            'messages' => $this->threadService->getThread($request),
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
            'replyForm' => $this->createForm(SupportReplyType::class),
        ]);
    }

    #[Route('/mark-read/{id}', name: 'app_admin_support_mark_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markRead(Request $httpRequest, int $id): Response
    {
        $request = $this->requireRequest($id);
        $this->requireCsrf($httpRequest, 'app_admin_support_mark_read' . $id);

        $this->threadService->markRead($request);

        return $this->redirectToRoute('app_admin_support_list');
    }

    #[Route('/{id}/reply', name: 'app_admin_support_reply', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reply(Request $httpRequest, int $id): Response
    {
        $request = $this->requireRequest($id);

        $actingAdmin = $this->getUser();
        if (!$actingAdmin instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(SupportReplyType::class);
        $form->handleRequest($httpRequest);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'admin_support.flash_reply_invalid');
            return $this->redirectToRoute('app_admin_support_request_show', ['id' => $id]);
        }

        $this->threadService->answer($request, (string) $form->get('response')->getData(), $actingAdmin);
        if ($this->threadService->hasLostRequester($request)) {
            $this->addFlash('error', 'admin_support.flash_reply_no_user');
        }

        return $this->redirectToRoute('app_admin_support_request_show', ['id' => $id]);
    }

    #[Route('/{id}/resolve', name: 'app_admin_support_resolve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resolve(Request $httpRequest, int $id): Response
    {
        $request = $this->requireRequest($id);
        $this->requireCsrf($httpRequest, 'app_admin_support_resolve' . $id);

        $this->threadService->resolve($request);

        return $this->redirectToRoute('app_admin_support_request_show', ['id' => $id]);
    }

    #[Route('/{id}/reopen', name: 'app_admin_support_reopen', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reopen(Request $httpRequest, int $id): Response
    {
        $request = $this->requireRequest($id);
        $this->requireCsrf($httpRequest, 'app_admin_support_reopen' . $id);

        $this->threadService->reopen($request);

        return $this->redirectToRoute('app_admin_support_request_show', ['id' => $id]);
    }

    #[Route('/{id}/invite-admins', name: 'app_admin_support_invite_admins', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function inviteAdmins(Request $httpRequest, int $id): Response
    {
        $request = $this->requireRequest($id);
        $this->requireCsrf($httpRequest, 'app_admin_support_invite_admins' . $id);

        $steward = $this->getUser();
        if (!$steward instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$request->canInviteAdmins()) {
            return $this->redirectToRoute('app_admin_support_request_show', ['id' => $id]);
        }

        $this->threadService->inviteAdmins($request, $steward);
        $this->supportInvitationEmail->send(['request' => $request]);

        $this->addFlash('success', 'admin_support.flash_admins_invited');

        return $this->redirectToRoute('app_admin_support_request_show', ['id' => $id]);
    }

    private function requireRequest(int $id): SupportRequest
    {
        $request = $this->threadService->findRequest($id);
        if (!$request instanceof SupportRequest || !$this->visibilityResolver->canView($request)) {
            throw $this->createNotFoundException();
        }

        return $request;
    }

    private function requireCsrf(Request $httpRequest, string $intention): void
    {
        if (!$this->isCsrfTokenValid($intention, (string) $httpRequest->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
    }
}
