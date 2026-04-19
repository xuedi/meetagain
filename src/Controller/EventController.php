<?php declare(strict_types=1);

namespace App\Controller;

use App\Activity\ActivityService;
use App\Activity\Messages\CommentedOnEvent;
use App\Activity\Messages\RsvpNo;
use App\Activity\Messages\RsvpYes;
use App\Entity\Comment;
use App\Entity\Event;
use App\Enum\EventRsvpFilter;
use App\Enum\EventSortFilter;
use App\Enum\EventTimeFilter;
use App\Enum\EventTileLocation;
use App\Enum\EventType;
use App\FeaturedEventProviderInterface;
use App\Filter\Event\EventFilterService;
use App\Form\CommentType;
use App\Form\EventFilterType;
use App\Repository\CommentRepository;
use App\Repository\EventRepository;
use App\Service\Event\EventService;
use App\Service\Seo\CanonicalUrlService;
use App\Service\Seo\EventSchemaService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
final class EventController extends AbstractController
{
    public const string ROUTE_EVENT = 'app_event';
    public const string ROUTE_FEATURED = 'app_event_featured';

    public function __construct(
        private readonly ActivityService $activityService,
        private readonly EventService $eventService,
        private readonly EventRepository $repo,
        private readonly CommentRepository $comments,
        private readonly EventFilterService $eventFilterService,
        private readonly EventSchemaService $eventSchemaService,
        private readonly CanonicalUrlService $canonicalUrlService,
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
        $time = $data['time'] ?? EventTimeFilter::Future;
        $sort = $data['sort'] ?? EventSortFilter::OldToNew;
        $type = $data['type'] ?? EventType::All;
        $rsvp = $data['rsvp'] ?? EventRsvpFilter::All;

        // Apply content filtering from all registered filters
        $filterResult = $this->eventFilterService->getEventIdFilter();
        $allowedEventIds = $filterResult->getEventIds();

        $providedFeatured = $this->getProvidedFeaturedEvents();
        $hasFeatured = $providedFeatured !== null
            ? $providedFeatured !== []
            : $this->repo->findFeatured($allowedEventIds) !== [];

        return $this->render(
            'events/index.html.twig',
            [
                'structuredList' => $this->eventService->getFilteredList(
                    $time,
                    $sort,
                    $type,
                    $rsvp,
                    $this->getUser(),
                    $allowedEventIds,
                ),
                'filter' => $form,
                'hasFeatured' => $hasFeatured,
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

        if ($event->findTranslation($request->getLocale()) === null) {
            return $this->redirectToRoute(self::ROUTE_EVENT);
        }

        $form = $this->createForm(CommentType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->isGranted('event.comment', $event)) {
                $this->addFlash('warning', 'This event is for group members only.');

                return $this->redirectToRoute('app_event_details', ['id' => $id]);
            }
            $comment = new Comment();
            $comment->setUser($this->getAuthedUser());
            $comment->setEvent($event);
            $comment->setContent($form->getData()['comment']);
            $comment->setCreatedAt(new DateTimeImmutable());
            $em->persist($comment);
            $em->flush();

            $this->activityService->log(CommentedOnEvent::TYPE, $this->getAuthedUser(), ['event_id' => $id]);

            $form = $this->createForm(CommentType::class);
        }

        if (!$this->getUser() instanceof UserInterface) {
            $request->getSession()->set('redirectUrl', $request->getRequestUri());
        }

        $canonicalUrl = $this->canonicalUrlService->getCanonicalUrl($request);
        $locale = $request->getLocale();

        return $this->render(
            'events/details.html.twig',
            [
                'commentForm' => $form,
                'pluginTiles' => $id ? $this->eventService->getPluginEventTiles($id, EventTileLocation::Sidebar) : [],
                'pluginBottomSidebarTiles' => $id ? $this->eventService->getPluginEventTiles($id, EventTileLocation::BottomSidebar) : [],
                'comments' => $this->comments->findByEventWithUser($id),
                'event' => $event,
                'user' => $this->getUser() instanceof UserInterface ? $this->getAuthedUser() : null,
                'json_ld' => $this->eventSchemaService->buildSchema($event, $canonicalUrl, $locale),
                'breadcrumbs' => [
                    ['label' => 'Home', 'url' => $this->generateUrl('app_default', ['_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL)],
                    ['label' => 'Events', 'url' => $this->generateUrl('app_event', ['_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL)],
                    ['label' => $event->getTitle($locale)],
                ],
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

        $filterResult = $this->eventFilterService->getEventIdFilter();
        $allowedEventIds = $filterResult->getEventIds();
        $lastEvents = $this->repo->getPastEvents(3, $allowedEventIds);

        if ($featuredEvents === null) {
            $featuredEvents = $this->repo->findFeatured($allowedEventIds);
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
            if (!$provider->shouldProvide()) {
                continue;
            }

            return $provider->getFeaturedEvents();
        }

        return null;
    }

    #[IsGranted('ROLE_USER')]
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
        if (!$this->isGranted('event.rsvp', $event)) {
            $this->addFlash('warning', 'This event is for group members only.');

            return $this->redirectToRoute('app_event_details', ['id' => $event->getId()]);
        }
        $event->toggleRsvp($this->getAuthedUser());
        $em->persist($event);
        $em->flush();

        $type = $event->hasRsvp($user) ? RsvpYes::TYPE : RsvpNo::TYPE;
        $this->activityService->log($type, $user, ['event_id' => $event->getId()]);

        return $this->redirectToRoute('app_event_details', ['id' => $event->getId()]);
    }

    #[IsGranted('ROLE_USER')]
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
