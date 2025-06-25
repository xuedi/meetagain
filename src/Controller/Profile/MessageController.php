<?php declare(strict_types=1);

namespace App\Controller\Profile;

use App\Controller\AbstractController;
use App\Entity\ActivityType;
use App\Entity\Message;
use App\Entity\User;
use App\Form\CommentType;
use App\Service\ActivityService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MessageController extends AbstractController
{
    public function __construct(private readonly ActivityService $activityService)
    {
    }

    #[Route('/profile/messages/{id}', name: 'app_profile_messages', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em, ?int $id = null): Response
    {
        $form = null;
        $user = $this->getAuthedUser();
        $msgRepo = $em->getRepository(Message::class);
        $userRepo = $em->getRepository(User::class);
        $messages = null;

        $conversationPartner = $userRepo->findOneBy(['id' => $id]);
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

                $em->persist($msg);
                $em->flush();

                $this->activityService->log(ActivityType::SendMessage, $user, ['user_id' => $conversationPartner->getId()]);
            }
            // preRender then flush when no new messages
            $messages = $msgRepo->getMessages($user, $conversationPartner);
            $msgRepo->markConversationRead($user, $conversationPartner);
            if (!$msgRepo->hasNewMessages($user)) {
                $request->getSession()->set('hasNewMessage', false);
            }
        }

        $conversationPartner = $userRepo->findOneBy(['id' => $id]);
        return $this->render('profile/messages/index.html.twig', [
            'conversationsId' => $id,
            'conversations' => $msgRepo->getConversations($user, $id),
            'messages' => $messages,
            'friends' => $userRepo->getFriends($user),
            'user' => $user,
            'form' => $form,
        ]);
    }
}
