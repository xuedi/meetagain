<?php declare(strict_types=1);

namespace Tests\Functional\Repository;

use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Entity\Location;
use App\Entity\User;
use App\Enum\EventRsvpFilter;
use App\Enum\EventSortFilter;
use App\Enum\EventStatus;
use App\Enum\EventTimeFilter;
use App\Enum\EventType;
use App\Repository\EventRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EventRepositoryLocaleTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private EventRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->repo = $container->get(EventRepository::class);
    }

    public function testAnEventMissingTheLocaleIsExcludedFromTheListButKeptWhenUnfiltered(): void
    {
        // Arrange
        $event = $this->createEnglishOnlyEvent();

        // Act
        $unfiltered = $this->findAll(null);
        $french = $this->findAll('fr');

        // Assert
        static::assertContains($event, $unfiltered);
        static::assertNotContains($event, $french);
    }

    public function testAFeaturedEventMissingTheLocaleIsExcludedFromTheFeaturedList(): void
    {
        // Arrange
        $event = $this->createEnglishOnlyEvent();

        // Act & Assert
        static::assertContains($event, $this->repo->findFeatured());
        static::assertNotContains($event, $this->repo->findFeatured(null, 'fr'));
    }

    public function testAPastEventMissingTheLocaleIsExcludedFromTheRecentList(): void
    {
        // Arrange
        $event = $this->createEnglishOnlyEvent(new DateTime('-1 hour'));

        // Act
        $recent = $this->repo->getPastEvents(1, null, 'fr');

        // Assert
        static::assertNotContains($event, $recent);
        static::assertContains($event, $this->repo->getPastEvents(1));
    }

    #[DataProvider('provideLocales')]
    public function testFindFeaturedReturnsOnlyEventsTranslatedIntoTheLocale(string $locale): void
    {
        // Act
        $featured = $this->repo->findFeatured(null, $locale);

        // Assert
        static::assertNotSame([], $featured);
        $this->assertAllTranslatedInto($featured, $locale);
    }

    #[DataProvider('provideLocales')]
    public function testGetPastEventsReturnsOnlyEventsTranslatedIntoTheLocale(string $locale): void
    {
        // Act
        $past = $this->repo->getPastEvents(3, null, $locale);

        // Assert
        $this->assertAllTranslatedInto($past, $locale);
    }

    #[DataProvider('provideLocales')]
    public function testFindByFiltersReturnsOnlyEventsTranslatedIntoTheLocale(string $locale): void
    {
        // Act
        $events = $this->findAll($locale);

        // Assert
        static::assertNotSame([], $events);
        $this->assertAllTranslatedInto($events, $locale);
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

    /**
     * @return array<Event>
     */
    private function findAll(?string $translatedIn): array
    {
        return $this->repo->findByFilters(
            EventTimeFilter::All,
            EventSortFilter::OldToNew,
            EventType::All,
            null,
            EventRsvpFilter::All,
            null,
            $translatedIn,
        );
    }

    /**
     * @param array<Event> $events
     */
    private function assertAllTranslatedInto(array $events, string $locale): void
    {
        foreach ($events as $event) {
            static::assertNotNull(
                $event->findTranslation($locale),
                sprintf('Event %d has no %s translation', (int) $event->getId(), $locale),
            );
        }
    }

    private function createEnglishOnlyEvent(?DateTime $start = null): Event
    {
        $user = $this->em->getRepository(User::class)->findOneBy([]);
        $location = $this->em->getRepository(Location::class)->findOneBy([]);
        static::assertInstanceOf(User::class, $user);
        static::assertInstanceOf(Location::class, $location);

        $event = new Event();
        $event->setInitial(true);
        $event->setStart($start ?? new DateTime('+1 year'));
        $event->setUser($user);
        $event->setLocation($location);
        $event->setType(EventType::Regular);
        $event->setStatus(EventStatus::Published);
        $event->setFeatured(true);
        $event->setCreatedAt(new DateTimeImmutable());
        $this->em->persist($event);

        $translation = new EventTranslation();
        $translation->setEvent($event);
        $translation->setLanguage('en');
        $translation->setTitle('English only');
        $translation->setTeaser('English only');
        $translation->setDescription('English only');
        $this->em->persist($translation);

        $this->em->flush();

        return $event;
    }
}
