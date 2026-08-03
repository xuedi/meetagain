<?php declare(strict_types=1);

namespace App\Controller;

use App\Activity\ActivityService;
use App\Activity\Messages\RsvpNo;
use App\Activity\Messages\RsvpYes;
use App\Entity\Event;
use App\Enum\EventRsvpFilter;
use App\Enum\EventSortFilter;
use App\Enum\EventTileLocation;
use App\Enum\EventTimeFilter;
use App\Enum\EventType;
use App\FeaturedEventProviderInterface;
use App\Filter\Event\EventFilterService;
use App\Form\EventFilterType;
use App\Repository\EventRepository;
use App\Security\Permission\Attribute\PermissionAttribute;
use App\Service\Event\CalendarFeedService;
use App\Service\Event\EventService;
use App\Service\Item\AssociationService;
use App\Service\Item\AttachControlBuilder;
use App\Service\Seo\BreadcrumbBuilder;
use App\Service\Seo\CanonicalUrlService;
use App\Service\Seo\EventSchemaService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class EventController extends AbstractController
{
    public const string ROUTE_EVENT = 'app_event';
    public const string ROUTE_FEATURED = 'app_event_featured';
    public const string ROUTE_CALENDAR_FEED = 'app_event_calendar_feed';
    public const string ROUTE_CALENDAR_EVENT = 'app_event_calendar_download';

    private const int CALENDAR_MAX_AGE = 3600;

    public function __construct(
        private readonly ActivityService $activityService,
        private readonly EventService $eventService,
        private readonly CalendarFeedService $calendarFeedService,
        private readonly EventRepository $repo,
        private readonly EventFilterService $eventFilterService,
        private readonly EventSchemaService $eventSchemaService,
        private readonly CanonicalUrlService $canonicalUrlService,
        private readonly AssociationService $itemAssociationService,
        private readonly AttachControlBuilder $itemAttachControlBuilder,
        private readonly BreadcrumbBuilder $breadcrumbBuilder,
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

        $filterResult = $this->eventFilterService->getEventIdFilter();
        $allowedEventIds = $filterResult->getEventIds();

        $providedFeatured = $this->getProvidedFeaturedEvents();
        $hasFeatured = $providedFeatured !== null ? $providedFeatured !== [] : $this->repo->findFeatured($allowedEventIds) !== [];

        return $this->render(
            'events/index.html.twig',
            [
                'structuredList' => $this->eventService->getFilteredList($time, $sort, $type, $rsvp, $this->getUser(), $allowedEventIds),
                'filter' => $form,
                'hasFeatured' => $hasFeatured,
            ],
            $response,
        );
    }

    #[Route('/event/{id}', name: 'app_event_details', requirements: ['id' => '\d+'])]
    public function details(Request $request, int $id): Response
    {
        if (!$this->eventFilterService->isEventAccessible($id)) {
            throw $this->createNotFoundException();
        }

        $response = $this->getResponse();
        $event = $this->repo->findOneForDetails($id);
        if (!$event instanceof Event) {
            throw $this->createNotFoundException();
        }

        if ($event->findTranslation($request->getLocale()) === null) {
            return $this->redirectToRoute(self::ROUTE_EVENT);
        }

        $canonicalUrl = $this->canonicalUrlService->getCanonicalUrl($request);
        $locale = $request->getLocale();

        return $this->render(
            'events/details.html.twig',
            [
                'itemCells' => $this->eventService->getRenderedItemCells($id),
                'attachControl' => $this->isGranted('ROLE_ORGANIZER') ? $this->itemAttachControlBuilder->build($id) : null,
                'pluginTiles' => $id ? $this->eventService->getPluginEventTiles($id, EventTileLocation::Sidebar) : [],
                'pluginBottomSidebarTiles' => $id ? $this->eventService->getPluginEventTiles($id, EventTileLocation::BottomSidebar) : [],
                'event' => $event,
                'json_ld' => $this->eventSchemaService->buildSchema($event, $canonicalUrl, $locale),
                'breadcrumbs' => $this->breadcrumbBuilder->build(self::ROUTE_EVENT, 'chrome.menu_events', $event->getTitle($locale)),
            ],
            $response,
        );
    }

    #[Route('/events.ics', name: self::ROUTE_CALENDAR_FEED, methods: ['GET'])]
    public function calendarFeed(Request $request): Response
    {
        $body = $this->calendarFeedService->renderFeed($request->getHost(), $request->getLocale());

        $response = new Response($body, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="events.ics"',
        ]);
        $response->setPublic();
        $response->setMaxAge(self::CALENDAR_MAX_AGE);
        $response->setEtag(hash('xxh128', $body));
        $response->isNotModified($request);

        return $response;
    }

    #[Route('/event/{id}.ics', name: self::ROUTE_CALENDAR_EVENT, requirements: ['id' => '\d+'], methods: ['GET'])]
    public function calendarEvent(Request $request, int $id): Response
    {
        if (!$this->eventFilterService->isEventAccessible($id)) {
            throw $this->createNotFoundException();
        }

        $event = $this->repo->findOneForDetails($id);
        if (!$event instanceof Event) {
            throw $this->createNotFoundException();
        }

        $body = $this->calendarFeedService->renderEvent($event, $request->getHost(), $request->getLocale());
        if ($body === null) {
            throw $this->createNotFoundException();
        }

        return new Response($body, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="event-%d.ics"', $id),
        ]);
    }

    #[Route('/event/featured/', name: self::ROUTE_FEATURED)]
    public function featured(): Response
    {
        $response = $this->getResponse();

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
     * @return array<Event>|null
     */
    private function getProvidedFeaturedEvents(): ?array
    {
        $providers = iterator_to_array($this->featuredEventProviders);

        usort($providers, static fn(FeaturedEventProviderInterface $a, FeaturedEventProviderInterface $b): int => $b->getPriority() <=> $a->getPriority());

        foreach ($providers as $provider) {
            if (!$provider->shouldProvide()) {
                continue;
            }

            return $provider->getFeaturedEvents();
        }

        return null;
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/event/toggleRsvp/{event}/', name: 'app_event_toggle_rsvp', methods: ['POST'])]
    public function toggleRsvp(Request $request, Event $event, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('app_event_toggle_rsvp' . $event->getId(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
        if ($event->isCanceled()) {
            $this->addFlash('error', 'events.flash_rsvp_canceled');

            return $this->redirectToRoute('app_event_details', ['id' => $event->getId()]);
        }
        if ($event->getStart() < new DateTimeImmutable()) {
            $this->addFlash('error', 'events.flash_rsvp_past');

            return $this->redirectToRoute('app_event_details', ['id' => $event->getId()]);
        }
        $user = $this->getAuthedUser();
        if (!$this->isGranted(PermissionAttribute::EVENT_RSVP, $event)) {
            $this->addFlash('warning', 'events.flash_group_only');

            return $this->redirectToRoute('app_event_details', ['id' => $event->getId()]);
        }
        $event->toggleRsvp($this->getAuthedUser());
        $em->persist($event);
        $em->flush();

        $type = $event->hasRsvp($user) ? RsvpYes::TYPE : RsvpNo::TYPE;
        $this->activityService->log($type, $user, ['event_id' => $event->getId()]);

        return $this->redirectToRoute('app_event_details', ['id' => $event->getId()]);
    }

    #[IsGranted('ROLE_ORGANIZER')]
    #[Route('/event/{id}/item/attach', name: 'app_item_attach', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function attachItem(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('app_item_attach' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
        $itemType = (string) $request->request->get('item_type');
        $itemId = $request->request->getInt('item_id');
        if ($itemType === '' || $itemId <= 0) {
            throw new BadRequestHttpException('Missing item type or id.');
        }

        $this->itemAssociationService->attach($id, $itemType, $itemId, (int) $this->getAuthedUser()->getId());

        return $this->redirectToRoute('app_event_details', ['id' => $id]);
    }

    #[IsGranted('ROLE_ORGANIZER')]
    #[Route('/event/{id}/item/detach', name: 'app_item_detach', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function detachItem(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('app_item_detach' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
        $itemType = (string) $request->request->get('item_type');
        $itemId = $request->request->getInt('item_id');
        if ($itemType === '' || $itemId <= 0) {
            throw new BadRequestHttpException('Missing item type or id.');
        }

        $this->itemAssociationService->detach($id, $itemType, $itemId);

        return $this->redirectToRoute('app_event_details', ['id' => $id]);
    }
}
