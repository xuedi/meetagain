<?php declare(strict_types=1);

namespace App\Controller;

use App\Filter\Event\EventFilterService;
use App\Form\EventGeoFilterContributor;
use App\Service\Event\EventService;
use DateTimeImmutable;
use Module\Geo\Contract\DirectoryInterface;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/map')]
final class MapController extends AbstractController
{
    public const int FEATURE_LIMIT = 500;
    private const array CARRIED = ['country', 'city', 'from', 'to'];

    public function __construct(
        private readonly EventGeoFilterContributor $geoFilter,
        private readonly EventFilterService $eventFilterService,
        private readonly EventService $events,
        private readonly DirectoryInterface $directory,
    ) {}

    #[Route('', name: 'app_map', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if (!$this->geoFilter->isMapShown()) {
            throw $this->createNotFoundException();
        }

        $builder = $this->container->get('form.factory')->createNamedBuilder('', FormType::class, null, [
            'csrf_protection' => false,
            'method' => 'GET',
        ])->add('from', DateType::class, [
            'widget' => 'single_text',
            'required' => false,
            'label' => 'map.label_from',
            'input' => 'string',
        ])->add('to', DateType::class, ['widget' => 'single_text', 'required' => false, 'label' => 'map.label_to', 'input' => 'string']);
        $this->geoFilter->addFields($builder);
        $form = $builder->getForm();
        $form->handleRequest($request);

        return $this->render(
            'map/index.html.twig',
            [
                'filter' => $form,
                'eventsUrl' => $this->generateUrl('app_map_events', array_filter(
                    array_intersect_key($request->query->all(), array_flip(self::CARRIED)),
                    static fn(mixed $value): bool => is_string($value) && $value !== '',
                )),
            ],
            $this->getResponse(),
        );
    }

    #[Route('/events.json', name: 'app_map_events', methods: ['GET'])]
    public function events(Request $request): JsonResponse
    {
        if (!$this->geoFilter->isMapShown()) {
            throw $this->createNotFoundException();
        }

        $locale = $request->getLocale();
        $from = $this->date($request->query->getString('from')) ?? new DateTimeImmutable();
        $to = $this->date($request->query->getString('to'))?->setTime(23, 59, 59);
        $found = $this->events->findForMap(
            $from,
            $to,
            $this->eventFilterService->getEventIdFilter()->getEventIds(),
            $this->geoFilter->cityIds($request->query->getString('country'), $request->query->getString('city')),
            $locale,
            self::FEATURE_LIMIT + 1,
        );
        $truncated = count($found) > self::FEATURE_LIMIT;
        $found = array_slice($found, 0, self::FEATURE_LIMIT);

        $cityIds = array_values(array_filter(array_map(static fn(array $row): ?int => $row['event']->getLocation()?->getCityId(), $found)));
        $cityNames = [];
        foreach ($this->directory->findCities(array_values(array_unique($cityIds)), $locale) as $city) {
            $cityNames[$city->id] = $city->name;
        }

        $features = [];
        foreach ($found as ['event' => $event, 'rsvpCount' => $rsvpCount]) {
            $location = $event->getLocation();
            if ($location === null) {
                continue;
            }
            $cityId = $location->getCityId();
            $features[] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [(float) $location->getLongitude(), (float) $location->getLatitude()]],
                'properties' => [
                    'id' => $event->getId(),
                    'title' => $event->getTitle($locale),
                    'start' => $event->getStart()->format(DATE_ATOM),
                    'venue' => $location->getName(),
                    'city' => $cityId !== null && isset($cityNames[$cityId]) ? $cityNames[$cityId] : (string) $location->getCity(),
                    'rsvpCount' => $rsvpCount,
                    'url' => $this->generateUrl('app_event_details', ['id' => $event->getId()]),
                ],
            ];
        }

        return new JsonResponse(['type' => 'FeatureCollection', 'features' => $features, 'truncated' => $truncated]);
    }

    private function date(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date === false ? null : $date;
    }
}
