<?php declare(strict_types=1);

namespace App\Controller\Profile;

use App\Controller\AbstractController;
use App\Entity\ActivityType;
use App\Entity\Message;
use App\Form\CommentType;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Service\ActivityService;
use App\Service\BlockingService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MessageController extends AbstractController
{
    public function __construct(
        private readonly ActivityService $activityService,
        private readonly EntityManagerInterface $em,
        private readonly MessageRepository $msgRepo,
        private readonly UserRepository $userRepo,
        private readonly BlockingService $blockingService,
    ) {
    }

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
                    $msg->setWasRead(true); // hopefully ^_^
                    $msg->setSender($this->getAuthedUser());
                    $msg->setReceiver($conversationPartner);
                    $msg->setCreatedAt(new DateTimeImmutable());
                    $msg->setContent($form->getData()['comment']);

                    $this->em->persist($msg);
                    $this->em->flush();

                    $this->activityService->log(ActivityType::SendMessage, $user, ['user_id' => $conversationPartner->getId()]);
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
}
