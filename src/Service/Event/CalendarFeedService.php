<?php declare(strict_types=1);

namespace App\Service\Event;

use App\Calendar\Entry;
use App\Calendar\Writer;
use App\Entity\Event;
use App\Filter\Event\EventFilterService;
use App\Repository\EventRepository;
use App\Service\Config\ConfigService;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class CalendarFeedService
{
    private const int WINDOW_MONTHS = 12;
    private const int MAX_ENTRIES = 500;
    private const int DEFAULT_DURATION_HOURS = 2;
    private const int DESCRIPTION_LIMIT = 2000;

    public function __construct(
        private EventRepository $eventRepository,
        private EventFilterService $eventFilterService,
        private Writer $writer,
        private UrlGeneratorInterface $urlGenerator,
        private ConfigService $configService,
        private TranslatorInterface $translator,
        private ClockInterface $clock,
        #[Autowire(service: 'cache.calendar_feed')]
        private CacheInterface $cache,
    ) {}

    public function renderFeed(string $host, string $locale): string
    {
        $entries = $this->cache->get(
            $this->cacheKey($host, $locale),
            function (ItemInterface $item) use ($locale): array {
                $item->expiresAt($this->hourBoundary()->modify('+1 hour'));

                return $this->buildEntries($locale);
            },
        );

        return $this->writer->write($entries, $this->calendarName($host, $locale), $this->hourBoundary());
    }

    public function renderEvent(Event $event, string $host, string $locale): ?string
    {
        $entry = $this->buildEntry($event, $locale);
        if (!$entry instanceof Entry) {
            return null;
        }

        return $this->writer->write([$entry], $this->calendarName($host, $locale), $this->hourBoundary());
    }

    /**
     * @return list<Entry>
     */
    private function buildEntries(string $locale): array
    {
        $events = $this->eventRepository->findForCalendarFeed($this->windowStart(), $this->windowEnd(), self::MAX_ENTRIES);
        if ($events === []) {
            return [];
        }

        $eventIds = array_values(array_filter(array_map(static fn(Event $event): ?int => $event->getId(), $events)));
        $accessibleIds = array_flip($this->eventFilterService->getAccessibleEventIds($eventIds));

        $entries = [];
        foreach ($events as $event) {
            if (!isset($accessibleIds[$event->getId()])) {
                continue;
            }

            $entry = $this->buildEntry($event, $locale);
            if (!$entry instanceof Entry) {
                continue;
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    private function buildEntry(Event $event, string $locale): ?Entry
    {
        $id = $event->getId();
        if ($id === null || $event->findTranslation($locale) === null) {
            return null;
        }

        $start = DateTimeImmutable::createFromInterface($event->getStart());
        $stop = $event->getStop();
        $end = $stop === null
            ? $start->modify('+' . self::DEFAULT_DURATION_HOURS . ' hours')
            : DateTimeImmutable::createFromInterface($stop);

        $url = $this->urlGenerator->generate(
            'app_event_details',
            ['_locale' => $locale, 'id' => $id],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return new Entry(
            uid: sprintf('event-%d@%s', $id, $this->uidDomain()),
            summary: $this->buildSummary($event, $locale),
            description: $this->buildDescription($event, $locale, $url),
            url: $url,
            start: $start,
            end: $end,
            location: $this->buildLocation($event),
            cancelled: $event->isCanceled(),
        );
    }

    private function buildSummary(Event $event, string $locale): string
    {
        $title = $event->getTitle($locale);
        if (!$event->isCanceled()) {
            return $title;
        }

        return $this->translator->trans('events.calendar_canceled_prefix', [], null, $locale) . ' ' . $title;
    }

    private function buildDescription(Event $event, string $locale, string $url): string
    {
        $teaser = $this->normalize($event->getTeaser($locale));
        $body = $teaser === '' ? $this->normalize($event->getDescription($locale)) : $teaser;

        if (mb_strlen($body) > self::DESCRIPTION_LIMIT) {
            $body = mb_substr($body, 0, self::DESCRIPTION_LIMIT) . '...';
        }

        return $body === '' ? $url : $body . "\n\n" . $url;
    }

    private function buildLocation(Event $event): ?string
    {
        $location = $event->getLocation();
        if ($location === null) {
            return null;
        }

        $town = trim(($location->getPostcode() ?? '') . ' ' . ($location->getCity() ?? ''));
        $parts = array_filter(
            [$location->getName(), $location->getStreet(), $town],
            static fn(?string $part): bool => $part !== null && trim($part) !== '',
        );

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function normalize(string $value): string
    {
        $withBreaks = preg_replace('#<(?:br\s*/?|/p|/div|/li|/h[1-6])\s*>#i', "\n", $value) ?? $value;
        $decoded = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $collapsed = preg_replace(['/[^\S\n]+/u', '/\s*\n\s*/u', '/\n{3,}/u'], [' ', "\n", "\n\n"], $decoded);

        return trim($collapsed ?? $decoded);
    }

    private function calendarName(string $host, string $locale): string
    {
        return $host . ' - ' . $this->translator->trans('events.calendar_name', [], null, $locale);
    }

    private function uidDomain(): string
    {
        $host = parse_url($this->configService->getHost(), PHP_URL_HOST);

        return is_string($host) ? $host : 'localhost';
    }

    private function cacheKey(string $host, string $locale): string
    {
        return sprintf('calendar_feed.%s.%s', hash('xxh128', $host), $locale);
    }

    private function hourBoundary(): DateTimeImmutable
    {
        $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));

        return $now->setTime((int) $now->format('G'), 0);
    }

    private function windowStart(): DateTimeImmutable
    {
        return $this->hourBoundary()->setTime(0, 0)->modify('-1 day');
    }

    private function windowEnd(): DateTimeImmutable
    {
        return $this->hourBoundary()->setTime(0, 0)->modify('+' . self::WINDOW_MONTHS . ' months');
    }
}
