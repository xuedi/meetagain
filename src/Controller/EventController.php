<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Event;
use App\Entity\EventFilterRsvp;
use App\Entity\EventFilterSort;
use App\Entity\EventFilterTime;
use App\Entity\EventTypes;
use App\Entity\Session\Consent;
use App\Form\CommentType;
use App\Form\EventFilterType;
use App\Repository\CommentRepository;
use App\Repository\EventRepository;
use App\Service\EventService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EventController extends AbstractController
{
    #[Route('/events', name: 'app_event')]
    public function index(EventService $eventService, Request $request): Response
    {
        $form = $this->createForm(EventFilterType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $time = $form->getData()['time'];
            $sort = $form->getData()['sort'];
            $type = $form->getData()['type'];
            $rsvp = $form->getData()['rsvp'];
        } else {
            $time = EventFilterTime::Future;
            $sort = EventFilterSort::OldToNew;
            $type = EventTypes::All;
            $rsvp = EventFilterRsvp::All;
        }

        return $this->render('events/index.html.twig', [
            'structuredList' => $eventService->getFilteredList($time, $sort, $type, $rsvp),
            'filter' => $form,
        ]);
    }

    #[Route('/event/{id}', name: 'app_event_details', requirements: ['id' => '\d+'])]
    public function details(EventRepository $repo, CommentRepository $comments, EntityManagerInterface $em, Request $request, ?int $id = null): Response
    {
        $form = $this->createForm(CommentType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $comment = new Comment();
            $comment->setUser($this->getAuthedUser());
            $comment->setEvent($repo->find($id));
            $comment->setContent($form->getData()['comment']);
            $comment->setCreatedAt(new DateTimeImmutable());
            $em->persist($comment);
            $em->flush();
        }

        $event = $repo->findOneBy(['id' => $id]);
        return $this->render('events/details.html.twig', [
            'commentForm' => $form,
            'comments' => $comments->findBy(['event' => $id]), // TODO: use custom repo with builder so userInfos are not lazy load
            'event' => $event,
            'user' => $this->getUser() instanceof \Symfony\Component\Security\Core\User\UserInterface ? $this->getAuthedUser() : null,
        ]);
    }

    #[Route('/event/toggleRsvp/{event}/', name: 'app_event_toggle_rsvp')]
    public function toggleRsvp(Event $event, EntityManagerInterface $em): Response
    {
        $event->toggleRsvp($this->getAuthedUser());
        $em->persist($event);
        $em->flush();

        return $this->redirectToRoute('app_event_details', ['id' => $event->getId()]);
    }

    #[Route('/event/{event}/deleteComment/{id}', name: 'app_event_delete_comment', requirements: ['id' => '\d+'])]
    public function deleteComment(Event $event, EntityManagerInterface $em, ?int $id = null): Response
    {
        $commentRepo = $em->getRepository(Comment::class);
        $comment = $commentRepo->findOneBy(['id' => $id, 'user' => $this->getAuthedUser()]);
        if ($comment !== null) {
            $em->remove($comment);
            $em->flush();
        }

        return $this->redirectToRoute('app_event_details', ['id' => $event->getId()]);
    }
}
