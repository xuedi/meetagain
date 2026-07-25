<?php declare(strict_types=1);

namespace App\Service\Seo;

use App\Entity\Event;
use App\Entity\EventSeries;
use App\Enum\EventStatus;
use App\Repository\EventRepository;
use App\Repository\EventSeriesRepository;
use App\ValueObject\CanonicalLane;
use App\ValueObject\CanonicalLaneStop;

/**
 * Builds the per-series, per-locale lanes of the canonical admin page. Presentation only - every
 * classification decision comes from the resolver.
 */
readonly class EventCanonicalOverviewService
{
    private const string ROOT_LABELS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function __construct(
        private EventSeriesRepository $seriesRepository,
        private EventRepository $eventRepository,
        private EventCanonicalResolver $resolver,
        private EventSimilarityService $similarityService,
    ) {}

    /**
     * @param array<int>|null $eventIds restrict to series containing at least one of these events
     * @return array<CanonicalLane>
     */
    public function getLanes(?int $seriesId = null, ?string $locale = null, bool $onlyBranched = false, ?array $eventIds = null): array
    {
        $this->resolver->clearMemo();

        $allowed = $eventIds === null ? null : array_flip($eventIds);

        $lanes = [];
        foreach ($this->seriesRepository->findAll() as $series) {
            if ($seriesId !== null && $series->getId() !== $seriesId) {
                continue;
            }

            $members = $this->eventRepository->findSeriesMembers([(int) $series->getId()]);
            if ($members === []) {
                continue;
            }

            if ($allowed !== null && !$this->anyMemberAllowed($members, $allowed)) {
                continue;
            }

            foreach ($this->localesOf($members) as $memberLocale) {
                if ($locale !== null && $memberLocale !== $locale) {
                    continue;
                }

                $lane = $this->buildLane($series, $members, $memberLocale);
                if ($onlyBranched && !$lane->isBranched()) {
                    continue;
                }

                $lanes[] = $lane;
            }
        }

        return $lanes;
    }

    /**
     * @return array<int, string> seriesId => name
     */
    public function getSeriesOptions(): array
    {
        $options = [];
        foreach ($this->seriesRepository->findAll() as $series) {
            $options[(int) $series->getId()] = $series->getName();
        }

        return $options;
    }

    /**
     * @param array<Event> $members
     * @param array<int, mixed> $allowed
     */
    private function anyMemberAllowed(array $members, array $allowed): bool
    {
        foreach ($members as $member) {
            if (isset($allowed[(int) $member->getId()])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<Event> $members
     */
    private function buildLane(EventSeries $series, array $members, string $locale): CanonicalLane
    {
        $localeMembers = array_values(array_filter($members, static fn(Event $m) => $m->findTranslation($locale) !== null));

        $rootLabels = [];
        $stops = [];
        foreach ($localeMembers as $member) {
            $root = $this->resolver->resolveRoot($member, $locale);
            $rootId = (int) $root->getId();
            $rootLabels[$rootId] ??= self::ROOT_LABELS[count($rootLabels) % strlen(self::ROOT_LABELS)];

            $stops[] = new CanonicalLaneStop(
                eventId: (int) $member->getId(),
                date: $member->getStart()->format('Y-m-d'),
                title: $member->getTitle($locale),
                marker: $this->resolver->getMarkerType($member, $locale),
                locked: $member->getStatus() === EventStatus::Locked,
                canceled: $member->isCanceled(),
                rootEventId: $rootId,
                rootLabel: $rootLabels[$rootId],
                percentChanged: $this->similarityService->compare($member, $root, $locale)->total,
            );
        }

        return new CanonicalLane(
            seriesId: (int) $series->getId(),
            seriesName: $series->getName(),
            locale: $locale,
            stops: $stops,
            rootCount: count($rootLabels),
        );
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
}
