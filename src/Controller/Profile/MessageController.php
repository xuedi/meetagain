<?php declare(strict_types=1);

namespace App\Controller\Profile;

use App\Activity\ActivityService;
use App\Activity\Messages\SendMessage;
use App\Controller\AbstractController;
use App\Entity\Message;
use App\Form\CommentType;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Service\Member\BlockingService;
use App\Service\Security\ContentSanitizer;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\TimeBundle\DateTimeFormatter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class MessageController extends AbstractController
{
    public function __construct(
        private readonly ActivityService $activityService,
        private readonly EntityManagerInterface $em,
        private readonly MessageRepository $msgRepo,
        private readonly UserRepository $userRepo,
        private readonly BlockingService $blockingService,
        private readonly DateTimeFormatter $dateTimeFormatter,
        private readonly ContentSanitizer $contentSanitizer,
    ) {}

    #[Route('/profile/messages/{id}', name: 'app_profile_messages', methods: ['GET', 'POST'])]
    public function index(Request $request, ?int $id = null): Response
    {
        $form = null;
        $user = $this->getAuthedUser();
        $messages = null;
        $isBlocked = false;

        $conversationPartner = $this->userRepo->findOneBy(['id' => $id]);
        if ($conversationPartner !== null) {
            // Check if either user has blocked the other
            $isBlocked = $this->blockingService->isBlocked($user, $conversationPartner);

            if (!$isBlocked) {
                $form = $this->createForm(CommentType::class);
                $form->handleRequest($request);
                if ($form->isSubmitted() && $form->isValid()) {
                    $msg = new Message();
                    $msg->setDeleted(false);
                    $msg->setWasRead(false);
                    $msg->setSender($this->getAuthedUser());
                    $msg->setReceiver($conversationPartner);
                    $msg->setCreatedAt(new DateTimeImmutable());
                    $msg->setContent($this->contentSanitizer->toPlainText((string) $form->getData()['comment']));

                    $this->em->persist($msg);
                    $this->em->flush();

                    $this->activityService->log(SendMessage::TYPE, $user, ['user_id' => $conversationPartner->getId()]);
                }
            }
            // preRender then flush when no new messages
            $messages = $this->msgRepo->getMessages($user, $conversationPartner);
            $this->msgRepo->markConversationRead($user, $conversationPartner);
            if (!$this->msgRepo->hasNewMessages($user)) {
                $request->getSession()->set('hasNewMessage', false);
            }
        }

        // Check if current user has blocked the partner (for showing block/unblock button)
        $hasBlockedPartner = $conversationPartner !== null && $this->blockingService->hasBlocked($user, $conversationPartner);

        // Get excluded user IDs for filtering conversations list
        $excludeUserIds = $this->blockingService->getExcludedUserIds($user);

        return $this->render('profile/messages/index.html.twig', [
            'conversationsId' => $id,
            'conversations' => $this->msgRepo->getConversations($user, $id, $excludeUserIds),
            'messages' => $messages,
            'friends' => $this->userRepo->getFriends($user),
            'user' => $user,
            'form' => $form,
            'isBlocked' => $isBlocked,
            'hasBlockedPartner' => $hasBlockedPartner,
        ]);
    }

    #[Route('/profile/messages/{id}/edit', name: 'app_profile_messages_edit', methods: ['POST'])]
    public function edit(Request $request, int $id): JsonResponse
    {
        if (!$this->isCsrfTokenValid('message_edit', $request->request->getString('_token'))) {
            return new JsonResponse(['error' => 'profile_messages.edit_csrf'], 403);
        }

        $user = $this->getAuthedUser();
        $message = $this->msgRepo->findEditableForSender($id, $user, new DateTimeImmutable());
        if ($message === null) {
            return new JsonResponse(['error' => 'profile_messages.edit_window_expired'], 403);
        }

        $trimmed = trim($request->request->getString('content'));
        if ($trimmed === '') {
            return new JsonResponse(['error' => 'profile_messages.edit_empty'], 400);
        }
        if (mb_strlen($trimmed) > 5000) {
            return new JsonResponse(['error' => 'profile_messages.edit_too_long'], 400);
        }
        $sanitized = $this->contentSanitizer->toPlainText($trimmed);
        if ($sanitized === $message->getContent()) {
            return new JsonResponse(['error' => 'profile_messages.edit_no_change'], 400);
        }

        $editedAt = new DateTimeImmutable();
        $message->setContent($sanitized);
        $message->setEditedAt($editedAt);
        $this->em->flush();

        return new JsonResponse([
            'id' => $message->getId(),
            'content' => $message->getContent(),
            'editedAt' => $editedAt->format(DateTimeImmutable::ATOM),
            'editedAtFormatted' => $this->dateTimeFormatter->formatDiff($editedAt),
        ]);
    }
}
