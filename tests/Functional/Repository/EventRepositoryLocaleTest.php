<?php declare(strict_types=1);

namespace Tests\Functional\Repository;

use App\Entity\Event;
use App\Enum\EventRsvpFilter;
use App\Enum\EventSortFilter;
use App\Enum\EventTimeFilter;
use App\Enum\EventType;
use App\Repository\EventRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EventRepositoryLocaleTest extends KernelTestCase
{
    private EventRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repo = self::getContainer()->get(EventRepository::class);
    }

    #[DataProvider('provideLocales')]
    public function testFindFeaturedReturnsOnlyEventsTranslatedIntoTheLocale(string $locale): void
    {
        // Act
        $featured = $this->repo->findFeatured(null, $locale);

        // Assert
        static::assertNotSame([], $featured);
        foreach ($featured as $event) {
            static::assertNotNull($event->findTranslation($locale), sprintf('Event %d has no %s translation', (int) $event->getId(), $locale));
        }
    }

    #[DataProvider('provideLocales')]
    public function testGetPastEventsReturnsOnlyEventsTranslatedIntoTheLocale(string $locale): void
    {
        // Act
        $past = $this->repo->getPastEvents(3, null, $locale);

        // Assert
        foreach ($past as $event) {
            static::assertNotNull($event->findTranslation($locale), sprintf('Event %d has no %s translation', (int) $event->getId(), $locale));
        }
    }

    #[DataProvider('provideLocales')]
    public function testFindByFiltersReturnsOnlyEventsTranslatedIntoTheLocale(string $locale): void
    {
        // Act
        $events = $this->repo->findByFilters(EventTimeFilter::All, EventSortFilter::OldToNew, EventType::All, null, EventRsvpFilter::All, null, $locale);

        // Assert
        static::assertNotSame([], $events);
        foreach ($events as $event) {
            static::assertNotNull($event->findTranslation($locale), sprintf('Event %d has no %s translation', (int) $event->getId(), $locale));
        }
    }

    public function testOmittingTheLocaleKeepsUntranslatedEvents(): void
    {
        // Act
        $all = $this->repo->findByFilters(EventTimeFilter::All, EventSortFilter::OldToNew, EventType::All, null, EventRsvpFilter::All);
        $french = $this->repo->findByFilters(EventTimeFilter::All, EventSortFilter::OldToNew, EventType::All, null, EventRsvpFilter::All, null, 'fr');

        // Assert
        static::assertGreaterThan(count($french), count($all));
    }

    public function testTheFetchJoinedTranslationCollectionStaysComplete(): void
    {
        // Act
        $french = $this->repo->findFeatured(null, 'fr');

        // Assert
        $event = $french[0] ?? null;
        static::assertInstanceOf(Event::class, $event);
        static::assertNotNull($event->findTranslation('en'), 'Filtering by locale must not truncate the translation collection');
    }

    public static function provideLocales(): iterable
    {
        yield 'french' => ['fr'];
        yield 'chinese' => ['zh'];
    }
}
