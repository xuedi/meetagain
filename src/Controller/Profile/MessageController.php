<?php declare(strict_types=1);

namespace App\Controller\Profile;

use App\Controller\AbstractController;
use App\Entity\ActivityType;
use App\Entity\Message;
use App\Form\CommentType;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Service\ActivityService;
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
    ) {}

    #[Route('/profile/messages/{id}', name: 'app_profile_messages', methods: ['GET', 'POST'])]
    public function index(Request $request, null|int $id = null): Response
    {
        $form = null;
        $user = $this->getAuthedUser();
        $messages = null;

        $conversationPartner = $this->userRepo->findOneBy(['id' => $id]);
        if ($conversationPartner !== null) {
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

                $this->activityService->log(ActivityType::SendMessage, $user, ['user_id' =>
                    $conversationPartner->getId()]);
            }
            // preRender then flush when no new messages
            $messages = $this->msgRepo->getMessages($user, $conversationPartner);
            $this->msgRepo->markConversationRead($user, $conversationPartner);
            if (!$this->msgRepo->hasNewMessages($user)) {
                $request->getSession()->set('hasNewMessage', false);
            }
        }

        $this->userRepo->findOneBy(['id' => $id]);
        return $this->render('profile/messages/index.html.twig', [
            'conversationsId' => $id,
            'conversations' => $this->msgRepo->getConversations($user, $id),
            'messages' => $messages,
            'friends' => $this->userRepo->getFriends($user),
            'user' => $user,
            'form' => $form,
        ]);
    }
}
