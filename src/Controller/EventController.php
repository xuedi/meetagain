<?php declare(strict_types=1);

namespace App\Controller;

use App\Authorization\Action\ActionAuthorizationMessageService;
use App\Authorization\Action\ActionAuthorizationService;
use App\Entity\ActivityType;
use App\Entity\Comment;
use App\Entity\Event;
use App\Entity\EventFilterRsvp;
use App\Entity\EventFilterSort;
use App\Entity\EventFilterTime;
use App\Entity\EventTypes;
use App\Entity\User;
use App\Filter\Event\EventFilterService;
use App\Form\CommentType;
use App\Form\EventFilterType;
use App\Repository\CommentRepository;
use App\Repository\EventRepository;
use App\Service\Activity\ActivityService;
use App\Service\Event\EventService;
use App\FeaturedEventProviderInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
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
        private readonly EventFilterService $eventFilterService,
        private readonly ActionAuthorizationService $actionAuthService,
        private readonly ActionAuthorizationMessageService $authMessageService,
        #[AutowireIterator(FeaturedEventProviderInterface::class)]
        private readonly iterable $featuredEventProviders = [],
    ) {}

    #[Route('/events', name: self::ROUTE_EVENT)]
    public function index(Request $request): Response
    {
        $response = $this->getResponse();
        $form = $this->createForm(EventFilterType::class);
        $form->handleRequest($request);

        $data = $form->getData() ?? [];
        $time = $data['time'] ?? EventFilterTime::Future;
        $sort = $data['sort'] ?? EventFilterSort::OldToNew;
        $type = $data['type'] ?? EventTypes::All;
        $rsvp = $data['rsvp'] ?? EventFilterRsvp::All;

        // Apply content filtering from all registered filters
        $filterResult = $this->eventFilterService->getEventIdFilter();

        return $this->render(
            'events/index.html.twig',
            [
                'structuredList' => $this->eventService->getFilteredList(
                    $time,
                    $sort,
                    $type,
                    $rsvp,
                    $this->getUser(),
                    $filterResult->getEventIds(),
                ),
                'filter' => $form,
            ],
            $response,
        );
    }

    #[Route('/event/{id}', name: 'app_event_details', requirements: ['id' => '\d+'])]
    public function details(EntityManagerInterface $em, Request $request, int $id): Response
    {
        // Check if event is accessible using composite filter
        if (!$this->eventFilterService->isEventAccessible($id)) {
            throw $this->createNotFoundException();
        }

        $response = $this->getResponse();
        $event = $this->repo->findOneForDetails($id);
        $form = $this->createForm(CommentType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->actionAuthService->isActionAllowed('event.comment', $id, $this->getUser())) {
                $unauthorizedMsg = $this->authMessageService->getUnauthorizedMessage(
                    'event.comment',
                    $id,
                    $this->getUser(),
                );
                $this->addFlash($unauthorizedMsg->type->value, $unauthorizedMsg->message);

                return $this->redirectToRoute('app_event_details', ['id' => $id]);
            }
            $comment = new Comment();
            $comment->setUser($this->getAuthedUser());
            $comment->setEvent($event);
            $comment->setContent($form->getData()['comment']);
            $comment->setCreatedAt(new DateTimeImmutable());
            $em->persist($comment);
            $em->flush();

            $this->activityService->log(ActivityType::CommentedOnEvent, $this->getAuthedUser(), ['event_id' => $id]);

            $form = $this->createForm(CommentType::class);
        }

        if (!$this->getUser() instanceof UserInterface) {
            $request->getSession()->set('redirectUrl', $request->getRequestUri());
        }

        return $this->render(
            'events/details.html.twig',
            [
                'commentForm' => $form,
                'pluginTiles' => $id ? $this->eventService->getPluginEventTiles($id) : [],
                'comments' => $this->comments->findByEventWithUser($id),
                'event' => $event,
                'user' => $this->getUser() instanceof UserInterface ? $this->getAuthedUser() : null,
            ],
            $response,
        );
    }

    #[Route('/event/featured/', name: self::ROUTE_FEATURED)]
    public function featured(): Response
    {
        $response = $this->getResponse();

        // Check if any plugin provides custom featured events list
        $featuredEvents = $this->getProvidedFeaturedEvents();

        if ($featuredEvents === null) {
            // No custom provider, use default logic with filtering
            $filterResult = $this->eventFilterService->getEventIdFilter();
            $allowedEventIds = $filterResult->getEventIds();

            $featuredEvents = $this->repo->findFeatured($allowedEventIds);

            $lastEvents = $this->repo->getPastEvents(3, $allowedEventIds);
        } else {
            // Provider handles filtering, just get past events with same filter
            $filterResult = $this->eventFilterService->getEventIdFilter();
            $allowedEventIds = $filterResult->getEventIds();
            $lastEvents = $this->repo->getPastEvents(3, $allowedEventIds);
        }

        return $this->render(
            'events/featured.html.twig',
            [
                'featured' => $featuredEvents,
                'last' => $lastEvents,
            ],
            $response,
        );
    }

    /**
     * Get featured events from a provider plugin if one should handle it.
     *
     * @return array<Event>|null
     */
    private function getProvidedFeaturedEvents(): ?array
    {
        $providers = iterator_to_array($this->featuredEventProviders);

        // Sort by priority (highest first)
        usort(
            $providers,
            static fn(
                FeaturedEventProviderInterface $a,
                FeaturedEventProviderInterface $b,
            ): int => $b->getPriority() <=> $a->getPriority(),
        );

        foreach ($providers as $provider) {
            if ($provider->shouldProvide()) {
                return $provider->getFeaturedEvents();
            }
        }

        return null;
    }

    #[Route('/event/toggleRsvp/{event}/', name: 'app_event_toggle_rsvp')]
    public function toggleRsvp(Event $event, EntityManagerInterface $em): Response
    {
        if ($event->isCanceled()) {
            $this->addFlash('error', 'You cannot RSVP to a canceled event.');

            return $this->redirectToRoute('app_event_details', ['id' => $event->getId()]);
        }
        if ($event->getStart() < new DateTimeImmutable()) {
            $this->addFlash('error', 'You cannot RSVP to an event that has already happened.');

            return $this->redirectToRoute('app_event_details', ['id' => $event->getId()]);
        }
        $user = $this->getAuthedUser();
        if (!$this->actionAuthService->isActionAllowed('event.rsvp', $event->getId(), $user)) {
            $unauthorizedMsg = $this->authMessageService->getUnauthorizedMessage('event.rsvp', $event->getId(), $user);
            $this->addFlash($unauthorizedMsg->type->value, $unauthorizedMsg->message);

            return $this->redirectToRoute('app_event_details', ['id' => $event->getId()]);
        }
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
