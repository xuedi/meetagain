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
use App\Entity\User;
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
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class EventController extends AbstractController
{
    public const string ROUTE_EVENT = 'app_event';
    public const string ROUTE_FEATURED = 'app_event_featured';

    public function __construct(
        private readonly ActivityService $activityService,
        private readonly EventService $eventService,
        private readonly EventRepository $repo,
        private readonly CommentRepository $comments,
        private readonly ImageService $imageService,
    ) {
    }

    #[Route('/events', name: self::ROUTE_EVENT)]
    public function index(Request $request): Response
    {
        $response = $this->getResponse();
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

        return $this->render(
            'events/index.html.twig',
            [
                'structuredList' => $this->eventService->getFilteredList($time, $sort, $type, $rsvp),
                'filter' => $form,
            ],
            $response,
        );
    }

    #[Route('/event/{id}', name: 'app_event_details', requirements: ['id' => '\d+'])]
    public function details(
        EntityManagerInterface $em,
        Request $request,
        null|int $id = null,
    ): Response {
        $response = $this->getResponse();
        $form = $this->createForm(CommentType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $comment = new Comment();
            $comment->setUser($this->getAuthedUser());
            $comment->setEvent($this->repo->find($id));
            $comment->setContent($form->getData()['comment']);
            $comment->setCreatedAt(new DateTimeImmutable());
            $em->persist($comment);
            $em->flush();

            $form = $this->createForm(CommentType::class);
        }

        if (!($this->getUser() instanceof UserInterface)) {
            $request->getSession()->set('redirectUrl', $request->getRequestUri());
        }

        $event = $this->repo->findOneBy(['id' => $id]);
        return $this->render(
            'events/details.html.twig',
            [
                'commentForm' => $form,
                'comments' => $this->comments->findByEventWithUser($id),
                'event' => $event,
                'user' => ($this->getUser() instanceof UserInterface) ? $this->getAuthedUser() : null,
            ],
            $response,
        );
    }

    #[Route('/event/upload/{event}', name: 'app_event_upload', methods: ['GET', 'POST'])]
    public function upload(
        Event $event,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        // Get user and ensure it's properly managed
        $sessionUser = $this->getAuthedUser();

        // Debug: Check if email is null in the session user
        if ($sessionUser->getEmail() === null) {
            throw new RuntimeException('Session user email is null! ID: ' . $sessionUser->getId());
        }

        // Find fresh user from database instead of relying on session user
        $user = $em->find(User::class, $sessionUser->getId());
        if ($user === null) {
            throw new RuntimeException('User not found in database! ID: ' . $sessionUser->getId());
        }

        $response = $this->getResponse();

        $form = $this->createForm(EventUploadType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $files = $form->get('files')->getData();
            foreach ($files as $uploadedFile) {
                $image = $this->imageService->upload($uploadedFile, $user, ImageType::EventUpload);
                $em->persist($image);
                $em->flush();
                $event->addImage($image);
                $this->imageService->createThumbnails($image);
            }
            $em->persist($event);
            $em->flush();

            $this->activityService->log(ActivityType::EventImageUploaded, $user, [
                'event_id' => $event->getId(),
                'images' => count($files),
            ]);

            return $this->redirectToRoute('app_event_details', ['id' => $event->getId()]);
        }

        return $this->render(
            'events/upload.html.twig',
            [
                'form' => $form,
            ],
            $response,
        );
    }

    #[Route('/event/featured/', name: self::ROUTE_FEATURED)]
    public function featured(): Response
    {
        $response = $this->getResponse();
        return $this->render(
            'events/featured.html.twig',
            [
                'featured' => $this->repo->findBy(['featured' => true], ['start' => 'ASC']),
                'last' => $this->repo->getPastEvents(),
            ],
            $response,
        );
    }

    #[Route('/event/toggleRsvp/{event}/', name: 'app_event_toggle_rsvp')]
    public function toggleRsvp(Event $event, EntityManagerInterface $em): Response
    {
        if ($event->getStart() < new DateTimeImmutable()) {
            $this->addFlash('error', 'You cannot RSVP to an event that has already happened.');
            return $this->redirectToRoute('app_event_details', ['id' => $event->getId()]);
        }
        $user = $this->getAuthedUser();
        $event->toggleRsvp($this->getAuthedUser());
        $em->persist($event);
        $em->flush();

        $type = $event->hasRsvp($user) ? ActivityType::RsvpYes : ActivityType::RsvpNo;
        $this->activityService->log($type, $user, ['event_id' => $event->getId()]);

        return $this->redirectToRoute('app_event_details', ['id' => $event->getId()]);
    }

    #[Route('/event/{event}/deleteComment/{id}', name: 'app_event_delete_comment', requirements: ['id' => '\d+'])]
    public function deleteComment(Event $event, EntityManagerInterface $em, null|int $id = null): Response
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
