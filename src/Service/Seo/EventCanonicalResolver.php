<?php declare(strict_types=1);

namespace App\Service\Seo;

use App\Entity\Event;
use App\Entity\EventSeries;
use App\Enum\EventCanonicalRootType;
use App\Repository\EventCanonicalRootRepository;
use App\Repository\EventRepository;

final class EventCanonicalResolver
{
    /** @var array<int, array<int, Event>> seriesId => members ordered by start */
    private array $membersBySeriesId = [];

    /** @var array<int, array<string, array<int, EventCanonicalRootType>>> seriesId => locale => eventId => type */
    private array $markersBySeriesId = [];

    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly EventCanonicalRootRepository $markerRepository,
    ) {}

    public function resolveRoot(Event $event, string $locale): Event
    {
        if ($this->getMarkerType($event, $locale) !== null) {
            return $event;
        }

        return $this->resolveBaselineRoot($event, $locale) ?? $event;
    }

    public function resolveBaselineRoot(Event $event, string $locale): ?Event
    {
        $series = $event->getSeries();
        if (!$series instanceof EventSeries) {
            return null;
        }

        $markers = $this->markersFor($series, $locale);
        $candidate = null;

        foreach ($this->membersFor($series) as $member) {
            if ($member->getId() === $event->getId()) {
                break;
            }
            if ($member->findTranslation($locale) === null) {
                continue;
            }
            if (($markers[$member->getId()] ?? null) === EventCanonicalRootType::Root) {
                $candidate = $member;
                continue;
            }
            $candidate ??= $member;
        }

        return $candidate;
    }

    public function getMarkerType(Event $event, string $locale): ?EventCanonicalRootType
    {
        $series = $event->getSeries();
        if (!$series instanceof EventSeries) {
            return null;
        }

        return $this->markersFor($series, $locale)[$event->getId()] ?? null;
    }

    /**
     * @param array<Event> $events
     * @param array<string> $locales
     * @return array<int, array<string, int>> eventId => locale => canonical root event id
     */
    public function resolveRootIds(array $events, array $locales): array
    {
        $this->primeSeries($events);

        $resolved = [];
        foreach ($events as $event) {
            $id = $event->getId();
            if ($id === null) {
                continue;
            }
            foreach ($locales as $locale) {
                $resolved[$id][$locale] = (int) $this->resolveRoot($event, $locale)->getId();
            }
        }

        return $resolved;
    }

    /**
     * @param array<Event> $events
     */
    public function primeSeries(array $events): void
    {
        $seriesIds = [];
        foreach ($events as $event) {
            $seriesId = $event->getSeries()?->getId();
            if ($seriesId === null || isset($this->membersBySeriesId[$seriesId])) {
                continue;
            }
            $seriesIds[$seriesId] = $seriesId;
        }

        if ($seriesIds === []) {
            return;
        }

        foreach (array_keys($seriesIds) as $seriesId) {
            $this->membersBySeriesId[$seriesId] = [];
            $this->markersBySeriesId[$seriesId] = [];
        }

        foreach ($this->eventRepository->findSeriesMembers(array_values($seriesIds)) as $member) {
            $this->membersBySeriesId[(int) $member->getSeries()?->getId()][] = $member;
        }

        foreach ($this->markerRepository->findBySeriesIds(array_values($seriesIds)) as $marker) {
            $event = $marker->getEvent();
            $seriesId = $event?->getSeries()?->getId();
            if ($seriesId === null) {
                continue;
            }
            $this->markersBySeriesId[$seriesId][$marker->getLocale()][(int) $event->getId()] = $marker->getType();
        }
    }

    public function clearMemo(): void
    {
        $this->membersBySeriesId = [];
        $this->markersBySeriesId = [];
    }

    /**
     * @return array<int, Event>
     */
    private function membersFor(EventSeries $series): array
    {
        $seriesId = (int) $series->getId();
        if (!isset($this->membersBySeriesId[$seriesId])) {
            $this->membersBySeriesId[$seriesId] = $this->eventRepository->findSeriesMembers([$seriesId]);
        }

        return $this->membersBySeriesId[$seriesId];
    }

    /**
     * @return array<int, EventCanonicalRootType> eventId => type
     */
    private function markersFor(EventSeries $series, string $locale): array
    {
        $seriesId = (int) $series->getId();
        if (!isset($this->markersBySeriesId[$seriesId])) {
            $byLocale = [];
            foreach ($this->markerRepository->findBySeries($seriesId) as $marker) {
                $eventId = $marker->getEvent()?->getId();
                if ($eventId === null) {
                    continue;
                }
                $byLocale[$marker->getLocale()][$eventId] = $marker->getType();
            }
            $this->markersBySeriesId[$seriesId] = $byLocale;
        }

        return $this->markersBySeriesId[$seriesId][$locale] ?? [];
    }
}
