<?php declare(strict_types=1);

namespace App\Controller\NonLocale\Api;

use App\Controller\AbstractController;
use App\Filter\Event\EventFilterService;
use App\Repository\EventRepository;
use App\Service\Api\EventApiSerializer;
use App\Service\Config\LanguageService;
use DateTimeImmutable;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EventApiController extends AbstractController
{
    private const int DEFAULT_LIMIT = 20;
    private const int MAX_LIMIT = 100;

    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly EventFilterService $eventFilterService,
        private readonly EventApiSerializer $serializer,
        private readonly LanguageService $languageService,
    ) {}

    #[Route('/api/events', name: 'app_api_events_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $locale = $this->resolveLocale($request);
        $limit = $this->clamp((int) $request->query->get('limit', (string) self::DEFAULT_LIMIT), 1, self::MAX_LIMIT);
        $offset = max(0, (int) $request->query->get('offset', '0'));

        $from = $this->parseDate($request->query->get('from', '')) ?? new DateTimeImmutable('now');
        $to = $this->parseDate($request->query->get('to', ''));

        $filterResult = $this->eventFilterService->getEventIdFilter();
        $restrictToIds = $filterResult->hasActiveFilter() ? ($filterResult->getEventIds() ?? []) : null;

        $result = $this->eventRepository->findPublicUpcoming($from, $to, $limit, $offset, $restrictToIds);

        $baseUrl = $request->getSchemeAndHttpHost();
        $items = array_map(
            fn($event) => $this->serializer->toSummary($event, $locale, $baseUrl),
            $result['items'],
        );

        return $this->jsonWithCaching([
            'items' => $items,
            'total' => $result['total'],
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    #[Route('/api/events/{id}', name: 'app_api_events_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id, Request $request): JsonResponse
    {
        if (!$this->eventFilterService->isEventAccessible($id)) {
            return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $event = $this->eventRepository->find($id);
        if ($event === null) {
            return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $locale = $this->resolveLocale($request);
        $baseUrl = $request->getSchemeAndHttpHost();

        return $this->jsonWithCaching($this->serializer->toDetail($event, $locale, $baseUrl));
    }

    private function resolveLocale(Request $request): string
    {
        $query = $request->query->get('locale', '');
        if ($query !== '' && $this->languageService->isValidCode($query)) {
            return $query;
        }

        $requestLocale = $request->getLocale();
        if ($requestLocale !== '' && $this->languageService->isValidCode($requestLocale)) {
            return $requestLocale;
        }

        return $this->languageService->getFilteredDefaultLocale();
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    private function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function jsonWithCaching(array $data): JsonResponse
    {
        $response = new JsonResponse($data);
        $response->headers->set('Cache-Control', 'public, max-age=60');

        return $response;
    }
}
