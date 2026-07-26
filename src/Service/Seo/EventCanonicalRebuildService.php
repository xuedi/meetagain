<?php declare(strict_types=1);

namespace App\Service\Seo;

use App\Entity\Event;
use App\Entity\EventCanonicalRoot;
use App\Entity\EventSeries;
use App\Enum\EventCanonicalRootType;
use App\Enum\EventStatus;
use App\Repository\EventCanonicalRootRepository;
use App\Repository\EventRepository;
use App\Repository\EventSeriesRepository;
use App\Service\Config\ConfigService;
use App\ValueObject\CanonicalRebuildSummary;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class EventCanonicalRebuildService
{
    public function __construct(
        private EventRepository $eventRepository,
        private EventSeriesRepository $seriesRepository,
        private EventCanonicalRootRepository $markerRepository,
        private EventSimilarityService $similarityService,
        private EventCanonicalResolver $resolver,
        private ConfigService $configService,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @return array<CanonicalRebuildSummary> one entry per locale present in the series
     */
    public function rebuildSeries(EventSeries $series): array
    {
        $seriesId = $series->getId();
        if ($seriesId === null) {
            return [];
        }

        $members = $this->eventRepository->findSeriesMembers([$seriesId]);
        $existingKeys = $this->markerKeys($this->markerRepository->findBySeries($seriesId));
        $this->markerRepository->deleteByEventIds($this->eventIds($members));

        $threshold = $this->configService->getEventCanonicalThreshold();
        $written = [];
        $summaries = [];

        foreach ($this->localesOf($members) as $locale) {
            $localeMembers = array_values(array_filter($members, static fn(Event $m) => $m->findTranslation($locale) !== null));
            $membersById = [];
            foreach ($localeMembers as $localeMember) {
                $membersById[(int) $localeMember->getId()] = $localeMember;
            }

            $roots = 0;
            $detached = 0;
            foreach ($this->classify($localeMembers, $locale, $threshold) as $eventId => $type) {
                $this->entityManager->persist($this->makeMarker($membersById[$eventId], $locale, $type));
                $written[$eventId . ':' . $locale] = true;
                $type === EventCanonicalRootType::Root ? $roots++ : $detached++;
            }

            $summaries[$locale] = new CanonicalRebuildSummary(
                locale: $locale,
                membersScanned: count($localeMembers),
                rootsWritten: $roots,
                detachedWritten: $detached,
                markersRemoved: 0,
            );
        }

        $this->entityManager->flush();
        $this->resolver->clearMemo();

        return array_values($this->applyRemovedCounts($summaries, $existingKeys, $written));
    }

    /**
     * @return array<CanonicalRebuildSummary> one entry per locale, summed over all series
     */
    public function rebuildAll(): array
    {
        $totals = [];
        foreach ($this->seriesRepository->findAll() as $series) {
            foreach ($this->rebuildSeries($series) as $summary) {
                $totals[$summary->locale] = isset($totals[$summary->locale])
                    ? $totals[$summary->locale]->merge($summary)
                    : $summary;
            }
        }

        $this->markerRepository->deleteOrphaned();
        $this->resolver->clearMemo();

        return array_values($totals);
    }

    public function refreshAfterEdit(Event $event, bool $appliedToFollowing): void
    {
        $series = $event->getSeries();
        $eventId = $event->getId();
        if (!$series instanceof EventSeries || $eventId === null) {
            return;
        }

        $this->resolver->clearMemo();
        $threshold = $this->configService->getEventCanonicalThreshold();
        $locales = $this->localesOf([$event]);

        foreach ($locales as $locale) {
            $existing = $this->markerRepository->findOneByEventAndLocale($eventId, $locale);
            $baseline = $this->resolver->resolveBaselineRoot($event, $locale);
            $diverged = $baseline instanceof Event
                && $this->similarityService->compare($event, $baseline, $locale)->exceeds($threshold);

            if (!$diverged) {
                if ($existing instanceof EventCanonicalRoot) {
                    $this->entityManager->remove($existing);
                }
                continue;
            }

            $marker = $existing ?? $this->makeMarker($event, $locale, EventCanonicalRootType::Root);
            $marker->setType($appliedToFollowing ? EventCanonicalRootType::Root : EventCanonicalRootType::Detached);
            $this->entityManager->persist($marker);
        }

        if ($appliedToFollowing) {
            $this->markerRepository->deleteByEventIds($this->syncedFollowerIds($event), $locales);
        }

        $this->entityManager->flush();
        $this->resolver->clearMemo();
    }

    /**
     * @return array<int>
     */
    private function syncedFollowerIds(Event $event): array
    {
        $seriesId = $event->getSeries()?->getId();
        if ($seriesId === null) {
            return [];
        }

        $followers = $this->eventRepository->findFollowUpEvents($seriesId, $event->getStart());
        $synced = array_filter(
            $followers,
            static fn(Event $follower) => $follower->getId() !== $event->getId() && $follower->getStatus() !== EventStatus::Locked,
        );

        return $this->eventIds($synced);
    }

    /**
     * @param array<Event> $members ordered by start, all with a translation in $locale
     * @return array<int, EventCanonicalRootType> eventId => marker type
     */
    private function classify(array $members, string $locale, int $threshold): array
    {
        if (count($members) < 2) {
            return [];
        }

        $markers = [];
        $runningRoot = $members[0];

        foreach ($members as $index => $member) {
            if ($index === 0) {
                continue;
            }

            $score = $this->similarityService->compare($member, $runningRoot, $locale);
            if (!$score->exceeds($threshold)) {
                continue;
            }

            if ($this->divergenceStuck($members, $index, $runningRoot, $locale)) {
                $markers[(int) $member->getId()] = EventCanonicalRootType::Root;
                $runningRoot = $member;
                continue;
            }

            $markers[(int) $member->getId()] = EventCanonicalRootType::Detached;
        }

        return $markers;
    }

    /**
     * @param array<Event> $members
     */
    private function divergenceStuck(array $members, int $index, Event $runningRoot, string $locale): bool
    {
        $diverged = $members[$index];
        $following = array_slice($members, $index + 1);
        if ($following === []) {
            return false;
        }

        $closerToDiverged = 0;
        foreach ($following as $later) {
            $changeVsDiverged = $this->similarityService->compare($later, $diverged, $locale)->total;
            $changeVsRoot = $this->similarityService->compare($later, $runningRoot, $locale)->total;
            if ($changeVsDiverged < $changeVsRoot) {
                $closerToDiverged++;
            }
        }

        return $closerToDiverged * 2 > count($following);
    }

    private function makeMarker(Event $event, string $locale, EventCanonicalRootType $type): EventCanonicalRoot
    {
        return (new EventCanonicalRoot())
            ->setEvent($event)
            ->setLocale($locale)
            ->setType($type)
            ->setCreatedAt(new DateTimeImmutable());
    }

    /**
     * @param array<Event> $members
     * @return array<string>
     */
    private function localesOf(array $members): array
    {
        $locales = [];
        foreach ($members as $member) {
            foreach ($member->getTranslation() as $translation) {
                $language = $translation->getLanguage();
                if ($language === null) {
                    continue;
                }
                $locales[$language] = $language;
            }
        }
        sort($locales);

        return $locales;
    }

    /**
     * @param array<Event> $members
     * @return array<int>
     */
    private function eventIds(array $members): array
    {
        return array_values(array_filter(array_map(static fn(Event $m) => $m->getId(), $members)));
    }

    /**
     * @param array<EventCanonicalRoot> $markers
     * @return array<string, string> "eventId:locale" => locale
     */
    private function markerKeys(array $markers): array
    {
        $keys = [];
        foreach ($markers as $marker) {
            $eventId = $marker->getEvent()?->getId();
            if ($eventId === null) {
                continue;
            }
            $keys[$eventId . ':' . $marker->getLocale()] = $marker->getLocale();
        }

        return $keys;
    }

    /**
     * @param array<string, CanonicalRebuildSummary> $summaries keyed by locale
     * @param array<string, string> $existingKeys
     * @param array<string, true> $writtenKeys
     * @return array<string, CanonicalRebuildSummary>
     */
    private function applyRemovedCounts(array $summaries, array $existingKeys, array $writtenKeys): array
    {
        foreach ($existingKeys as $key => $locale) {
            if (isset($writtenKeys[$key])) {
                continue;
            }
            $previous = $summaries[$locale] ?? new CanonicalRebuildSummary($locale, 0, 0, 0, 0);
            $summaries[$locale] = new CanonicalRebuildSummary(
                locale: $locale,
                membersScanned: $previous->membersScanned,
                rootsWritten: $previous->rootsWritten,
                detachedWritten: $previous->detachedWritten,
                markersRemoved: $previous->markersRemoved + 1,
            );
        }

        return $summaries;
    }
}
