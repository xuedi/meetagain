<?php declare(strict_types=1);

namespace App\Controller\Admin\Support;

use App\Activity\ActivityService;
use App\Activity\Messages\SendMessage;
use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionForm;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Emails\Types\SupportResponseEmail;
use App\Entity\Message;
use App\Entity\SupportRequest;
use App\Entity\User;
use App\Enum\SupportReplyChannel;
use App\Enum\SupportRequestStatus;
use App\Form\SupportReplyType;
use App\Repository\SupportRequestRepository;
use App\Repository\UserRepository;
use App\Service\Security\ContentSanitizer;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/support')]
final class RequestsController extends AbstractSupportController implements AdminNavigationInterface, AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly SupportRequestRepository $supportRequestRepo,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly SupportResponseEmail $supportResponseEmail,
        private readonly ActivityService $activityService,
        private readonly ContentSanitizer $contentSanitizer,
    ) {
        parent::__construct($translator, 'requests');
    }

    #[Route('', name: 'app_admin_support_list')]
    public function list(): Response
    {
        $requests = $this->supportRequestRepo->createQueryBuilder('sr')->orderBy('sr.createdAt', 'DESC')->getQuery()->getResult();

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
        $request = $this->supportRequestRepo->find($id);
        if (!$request instanceof SupportRequest) {
            throw $this->createNotFoundException();
        }

        [$statusVariant, $statusKey] = match (true) {
            $request->isNew() => ['is-warning', 'admin_support.status_new'],
            $request->isReplied() => ['is-success', 'admin_support.status_replied'],
            default => ['is-light', 'admin_support.status_read'],
        };

        $info = [
            new AdminTopInfoHtml(sprintf('<strong>%s</strong>', $request->getCreatedAt()->format('Y-m-d H:i:s'))),
            new AdminTopInfoHtml(sprintf('<span class="tag is-light is-medium">%s</span>', htmlspecialchars(
                $this->translator->trans($request->getContactType()->label()),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            ))),
            new AdminTopInfoHtml(sprintf(
                '<span class="tag %s is-medium">%s</span>',
                $statusVariant,
                htmlspecialchars($this->translator->trans($statusKey), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
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
        $actions[] = new AdminTopActionButton(
            label: $this->translator->trans('admin_support.button_back'),
            target: $this->generateUrl('app_admin_support_list'),
            icon: 'arrow-left',
        );

        $adminTop = new AdminTop(info: $info, actions: $actions);

        $matchedUser = $this->userRepository->findOneBy(['email' => $request->getEmail()]);
        $replyMode = $matchedUser instanceof User ? 'message' : 'email';

        return $this->render('admin/support/request_show.html.twig', [
            'active' => 'support',
            'request' => $request,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
            'replyMode' => $replyMode,
            'replyForm' => $this->createForm(SupportReplyType::class),
        ]);
    }

    #[Route('/mark-read/{id}', name: 'app_admin_support_mark_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markRead(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('app_admin_support_mark_read' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
        $request = $this->supportRequestRepo->find($id);
        if ($request instanceof SupportRequest) {
            $this->markRequestRead($request);
        }

        return $this->redirectToRoute('app_admin_support_list');
    }

    #[Route('/{id}/reply-email', name: 'app_admin_support_reply_email', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function replyEmail(Request $httpRequest, int $id): Response
    {
        $request = $this->supportRequestRepo->find($id);
        if (!$request instanceof SupportRequest) {
            throw $this->createNotFoundException();
        }
        if ($request->isReplied()) {
            $this->addFlash('error', 'admin_support.flash_reply_already');
            return $this->redirectToRoute('app_admin_support_request_show', ['id' => $id]);
        }

        $form = $this->createForm(SupportReplyType::class);
        $form->handleRequest($httpRequest);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'admin_support.flash_reply_invalid');
            return $this->redirectToRoute('app_admin_support_request_show', ['id' => $id]);
        }

        $response = $this->contentSanitizer->basic((string) $form->get('response')->getData());
        $this->supportResponseEmail->send(['request' => $request, 'response' => $response]);
        $this->markRequestReplied($request, $response, SupportReplyChannel::Email);
        $this->addFlash('success', 'admin_support.flash_reply_email_sent');

        return $this->redirectToRoute('app_admin_support_request_show', ['id' => $id]);
    }

    #[Route('/{id}/reply-message', name: 'app_admin_support_reply_message', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function replyMessage(Request $httpRequest, int $id): Response
    {
        $request = $this->supportRequestRepo->find($id);
        if (!$request instanceof SupportRequest) {
            throw $this->createNotFoundException();
        }
        if ($request->isReplied()) {
            $this->addFlash('error', 'admin_support.flash_reply_already');
            return $this->redirectToRoute('app_admin_support_request_show', ['id' => $id]);
        }

        $receiver = $this->userRepository->findOneBy(['email' => $request->getEmail()]);
        if (!$receiver instanceof User) {
            $this->addFlash('error', 'admin_support.flash_reply_no_user');
            return $this->redirectToRoute('app_admin_support_request_show', ['id' => $id]);
        }

        $form = $this->createForm(SupportReplyType::class);
        $form->handleRequest($httpRequest);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'admin_support.flash_reply_invalid');
            return $this->redirectToRoute('app_admin_support_request_show', ['id' => $id]);
        }

        $actingAdmin = $this->getUser();
        if (!$actingAdmin instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $response = $this->contentSanitizer->basic((string) $form->get('response')->getData());

        // The first admin to respond owns the conversation; later replies (from any admin) are
        // attributed to that admin so all support correspondence stays in one user-admin thread.
        $owner = $request->getRespondedBy();
        $isFirstResponse = !$owner instanceof User;
        if ($isFirstResponse) {
            $owner = $actingAdmin;

            $question = new Message();
            $question->setDeleted(false);
            $question->setWasRead(true);
            $question->setSender($receiver);
            $question->setReceiver($owner);
            $question->setCreatedAt($request->getCreatedAt());
            $question->setContent(Message::SUPPORT_QUESTION_MARKER . $request->getMessage());
            $this->em->persist($question);
        }

        $answer = new Message();
        $answer->setDeleted(false);
        $answer->setWasRead(false);
        $answer->setSender($owner);
        $answer->setReceiver($receiver);
        $answer->setCreatedAt(new DateTimeImmutable());
        $answer->setContent($response);
        $this->em->persist($answer);

        $request->setRespondedBy($owner);
        $this->markRequestReplied($request, $response, SupportReplyChannel::Message);

        $this->activityService->log(SendMessage::TYPE, $owner, ['user_id' => $receiver->getId()]);

        $this->addFlash('success', 'admin_support.flash_reply_message_sent');

        return $this->redirectToRoute('app_admin_support_request_show', ['id' => $id]);
    }

    private function markRequestRead(SupportRequest $request): void
    {
        $request->setStatus(SupportRequestStatus::Read);
        $this->em->persist($request);
        $this->em->flush();
    }

    private function markRequestReplied(SupportRequest $request, string $response, SupportReplyChannel $channel): void
    {
        $request->setStatus(SupportRequestStatus::Replied);
        $request->setResponse($response);
        $request->setReplyChannel($channel);
        $this->em->persist($request);
        $this->em->flush();
    }
}
