<?php declare(strict_types=1);

namespace Tests\Unit\Service\Seo;

use App\Entity\Event;
use App\Entity\EventCanonicalRoot;
use App\Entity\EventSeries;
use App\Entity\EventTranslation;
use App\Enum\EventCanonicalRootType;
use App\Repository\EventCanonicalRootRepository;
use App\Repository\EventRepository;
use App\Service\Seo\EventCanonicalResolver;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Stubs\EventSeriesStub;
use Tests\Unit\Stubs\EventStub;

class EventCanonicalResolverTest extends TestCase
{
    private function makeSeries(int $id = 1): EventSeries
    {
        $series = new EventSeriesStub();
        $series->setId($id);

        return $series;
    }

    /**
     * @param array<string> $locales
     */
    private function makeMember(int $id, ?EventSeries $series, array $locales = ['en', 'de'], string $day = '2026-01-01'): Event
    {
        $event = new EventStub();
        $event->setId($id);
        $event->setStart(new DateTime($day . ' 19:00'));
        $event->setSeries($series);

        foreach ($locales as $locale) {
            $translation = new EventTranslation();
            $translation->setLanguage($locale);
            $translation->setTitle('Title ' . $id);
            $translation->setDescription('Description ' . $id);
            $event->addTranslation($translation);
        }

        return $event;
    }

    private function makeMarker(Event $event, string $locale, EventCanonicalRootType $type): EventCanonicalRoot
    {
        return (new EventCanonicalRoot())
            ->setEvent($event)
            ->setLocale($locale)
            ->setType($type)
            ->setCreatedAt(new DateTimeImmutable('2026-01-01'));
    }

    /**
     * @param array<Event> $members
     * @param array<EventCanonicalRoot> $markers
     */
    private function createResolver(array $members, array $markers = []): EventCanonicalResolver
    {
        $eventRepository = $this->createStub(EventRepository::class);
        $eventRepository->method('findSeriesMembers')->willReturn($members);

        $markerRepository = $this->createStub(EventCanonicalRootRepository::class);
        $markerRepository->method('findBySeries')->willReturn($markers);
        $markerRepository->method('findBySeriesIds')->willReturn($markers);

        return new EventCanonicalResolver($eventRepository, $markerRepository);
    }

    public function testUnmarkedSeriesResolvesToItsFirstMember(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $first = $this->makeMember(1, $series, day: '2026-01-01');
        $third = $this->makeMember(3, $series, day: '2026-01-15');
        $resolver = $this->createResolver([$first, $this->makeMember(2, $series, day: '2026-01-08'), $third]);

        // Act
        $root = $resolver->resolveRoot($third, 'en');

        // Assert
        static::assertSame($first, $root);
    }

    public function testFirstMemberResolvesToItself(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $first = $this->makeMember(1, $series, day: '2026-01-01');
        $resolver = $this->createResolver([$first, $this->makeMember(2, $series, day: '2026-01-08')]);

        // Act & Assert
        static::assertSame($first, $resolver->resolveRoot($first, 'en'));
        static::assertNull($resolver->resolveBaselineRoot($first, 'en'));
    }

    public function testNearestPrecedingRootMarkerWins(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $first = $this->makeMember(1, $series, day: '2026-01-01');
        $branch = $this->makeMember(2, $series, day: '2026-01-08');
        $later = $this->makeMember(3, $series, day: '2026-01-15');
        $resolver = $this->createResolver(
            [$first, $branch, $later],
            [$this->makeMarker($branch, 'en', EventCanonicalRootType::Root)],
        );

        // Act & Assert
        static::assertSame($branch, $resolver->resolveRoot($later, 'en'));
        static::assertSame($branch, $resolver->resolveRoot($branch, 'en'));
        static::assertSame($first, $resolver->resolveBaselineRoot($branch, 'en'));
    }

    public function testDetachedMemberIsSelfCanonicalAndIgnoredByLaterMembers(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $first = $this->makeMember(1, $series, day: '2026-01-01');
        $oddOne = $this->makeMember(2, $series, day: '2026-01-08');
        $later = $this->makeMember(3, $series, day: '2026-01-15');
        $resolver = $this->createResolver(
            [$first, $oddOne, $later],
            [$this->makeMarker($oddOne, 'en', EventCanonicalRootType::Detached)],
        );

        // Act & Assert
        static::assertSame($oddOne, $resolver->resolveRoot($oddOne, 'en'));
        static::assertSame($first, $resolver->resolveRoot($later, 'en'));
    }

    public function testMarkersOfOneLocaleDoNotAffectAnother(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $first = $this->makeMember(1, $series, day: '2026-01-01');
        $branch = $this->makeMember(2, $series, day: '2026-01-08');
        $later = $this->makeMember(3, $series, day: '2026-01-15');
        $resolver = $this->createResolver(
            [$first, $branch, $later],
            [$this->makeMarker($branch, 'de', EventCanonicalRootType::Root)],
        );

        // Act & Assert
        static::assertSame($branch, $resolver->resolveRoot($later, 'de'));
        static::assertSame($first, $resolver->resolveRoot($later, 'en'));
    }

    public function testMemberWithoutTranslationInLocaleIsNotPartOfThatChain(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $englishOnly = $this->makeMember(1, $series, ['en'], '2026-01-01');
        $bothLocales = $this->makeMember(2, $series, ['en', 'de'], '2026-01-08');
        $later = $this->makeMember(3, $series, ['en', 'de'], '2026-01-15');
        $resolver = $this->createResolver([$englishOnly, $bothLocales, $later]);

        // Act & Assert
        static::assertSame($englishOnly, $resolver->resolveRoot($later, 'en'));
        static::assertSame($bothLocales, $resolver->resolveRoot($later, 'de'));
    }

    public function testRootWithoutTranslationInLocaleFallsBackToTheEarlierRoot(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $first = $this->makeMember(1, $series, ['en', 'de'], '2026-01-01');
        $englishOnlyRoot = $this->makeMember(2, $series, ['en'], '2026-01-08');
        $later = $this->makeMember(3, $series, ['en', 'de'], '2026-01-15');
        $resolver = $this->createResolver(
            [$first, $englishOnlyRoot, $later],
            [$this->makeMarker($englishOnlyRoot, 'en', EventCanonicalRootType::Root)],
        );

        // Act & Assert
        static::assertSame($englishOnlyRoot, $resolver->resolveRoot($later, 'en'));
        static::assertSame($first, $resolver->resolveRoot($later, 'de'));
    }

    public function testEventWithoutAnyTranslatedPredecessorIsSelfCanonical(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $englishOnly = $this->makeMember(1, $series, ['en'], '2026-01-01');
        $german = $this->makeMember(2, $series, ['de'], '2026-01-08');
        $resolver = $this->createResolver([$englishOnly, $german]);

        // Act & Assert
        static::assertSame($german, $resolver->resolveRoot($german, 'de'));
    }

    public function testOneOffEventResolvesToItself(): void
    {
        // Arrange
        $oneOff = $this->makeMember(7, null);
        $resolver = $this->createResolver([]);

        // Act & Assert
        static::assertSame($oneOff, $resolver->resolveRoot($oneOff, 'en'));
        static::assertNull($resolver->resolveBaselineRoot($oneOff, 'en'));
    }

    public function testStaleMarkerOnEventWithoutSeriesIsIgnored(): void
    {
        // Arrange
        $orphan = $this->makeMember(7, null);
        $resolver = $this->createResolver([], [$this->makeMarker($orphan, 'en', EventCanonicalRootType::Root)]);

        // Act & Assert
        static::assertNull($resolver->getMarkerType($orphan, 'en'));
        static::assertSame($orphan, $resolver->resolveRoot($orphan, 'en'));
    }

    public function testBatchResolutionReturnsRootIdsPerLocale(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $first = $this->makeMember(1, $series, day: '2026-01-01');
        $branch = $this->makeMember(2, $series, day: '2026-01-08');
        $later = $this->makeMember(3, $series, day: '2026-01-15');
        $resolver = $this->createResolver(
            [$first, $branch, $later],
            [$this->makeMarker($branch, 'de', EventCanonicalRootType::Root)],
        );

        // Act
        $resolved = $resolver->resolveRootIds([$first, $branch, $later], ['en', 'de']);

        // Assert
        static::assertSame(['en' => 1, 'de' => 1], $resolved[1]);
        static::assertSame(['en' => 1, 'de' => 2], $resolved[2]);
        static::assertSame(['en' => 1, 'de' => 2], $resolved[3]);
    }
}
