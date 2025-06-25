<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\ActivityType;
use App\Entity\Comment;
use App\Entity\Event;
use App\Entity\EventFilterRsvp;
use App\Entity\EventFilterSort;
use App\Entity\EventFilterTime;
use App\Entity\EventTypes;
use App\Entity\ImageType;
use App\Form\CommentType;
use App\Form\EventFilterType;
use App\Form\EventUploadType;
use App\Repository\CommentRepository;
use App\Repository\EventRepository;
use App\Service\ActivityService;
use App\Service\EventService;
use App\Service\ImageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class EventController extends AbstractController
{
    public function __construct(
        private readonly ActivityService $activityService
    ) {
    }

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

            $form = $this->createForm(CommentType::class);
        }

        if (!$this->getUser() instanceof UserInterface) {
            $request->getSession()->set('redirectUrl', $request->getRequestUri());
        }

        $event = $repo->findOneBy(['id' => $id]);
        return $this->render('events/details.html.twig', [
            'commentForm' => $form,
            'comments' => $comments->findBy(['event' => $id]),
            'event' => $event,
            'user' => $this->getUser() instanceof UserInterface ? $this->getAuthedUser() : null,
        ]);
    }

    #[Route('/event/upload/{event}', name: 'app_event_upload', methods: ['GET', 'POST'])]
    public function upload(Event $event, Request $request, ImageService $imageService, EntityManagerInterface $em): Response
    {
        $user = $this->getAuthedUser();

        $form = $this->createForm(EventUploadType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $files = $form->get('files')->getData();
            foreach ($files as $uploadedFile) {
                $image = $imageService->upload($uploadedFile, $user, ImageType::EventUpload);
                $em->persist($image);
                $em->flush();
                $event->addImage($image);
                $imageService->createThumbnails($image);
            }
            $em->persist($event);
            $em->flush();

            $this->activityService->log(
                ActivityType::EventImageUploaded,
                $user,
                [
                    'event_id' => $event->getId(),
                    'images' => count($files)
                ]
            );

            return $this->redirectToRoute('app_event_details', ['id' => $event->getId()]);
        }

        return $this->render('events/upload.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/event/featured/', name: 'app_event_featured')]
    public function featured(EventRepository $repo): Response
    {
        return $this->render('events/featured.html.twig', [
            'featured' => $repo->findBy(['featured' => true], ['start' => 'ASC']),
            'last' => $repo->getPastEvents(),
        ]);
    }

    #[Route('/event/toggleRsvp/{event}/', name: 'app_event_toggle_rsvp')]
    public function toggleRsvp(Event $event, EntityManagerInterface $em): Response
    {
        $user = $this->getAuthedUser();
        $event->toggleRsvp($this->getAuthedUser());
        $em->persist($event);
        $em->flush();

        $type = $event->hasRsvp($user) ? ActivityType::RsvpYes : ActivityType::RsvpNo;
        $this->activityService->log($type, $user, ['event_id' => $event->getId()]);

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
