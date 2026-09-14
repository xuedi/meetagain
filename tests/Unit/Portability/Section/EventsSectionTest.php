<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Section;

use App\Entity\Event;
use App\Entity\EventSeries;
use App\Entity\EventTranslation;
use App\Entity\Host;
use App\Entity\Location;
use App\Entity\User;
use App\Enum\EventInterval;
use App\Enum\EventStatus;
use App\Enum\EventType;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\Section\EventsSection;
use App\Repository\LocationRepository;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class EventsSectionTest extends SectionTestCase
{
    public function testAnEventSurvivesTheRoundTrip(): void
    {
        // Arrange
        $creator = $this->withId(new User(), 5);
        $creator->setEmail('host@example.org');
        $source = $this->withId(new Event(), 1);
        $source->setInitial(false);
        $source->setExternalRsvp(3);
        $source->addHost($this->withId(new Host(), 9));
        $source->setStart(new DateTime('2030-01-06 19:00'));
        $source->setStop(new DateTime('2030-01-06 22:00'));
        $source->setStatus(EventStatus::Published);
        $source->setType(EventType::cases()[0]);
        $source->setFeatured(true);
        $source->setLocation($this->withId(new Location(), 7));
        $source->setSeries($this->withId(new EventSeries(), 3));
        $source->setUser($creator);
        $source->addTranslation($this->translation('en', 'Go night', 'Bring a board', 'Every Monday'));
        $exported = $this->section([$source])->export(new Scope(users: [5 => 'user'], eventIds: [1]), $this->images());

        $context = $this->context();
        $venue = new Location();
        $series = new EventSeries();
        $host = new User();
        $eventHost = new Host();
        $context->mapRef(Location::class, 7, $venue);
        $context->mapRef(EventSeries::class, 3, $series);
        $context->mapRef(User::class, 'host@example.org', $host);
        $context->mapRef(Host::class, 9, $eventHost);

        // Act
        $this->section()->import($exported, $context);

        // Assert
        $event = $this->onlyPersisted(Event::class);
        static::assertSame('2030-01-06 19:00', $event->getStart()->format('Y-m-d H:i'));
        static::assertSame('2030-01-06 22:00', $event->getStop()?->format('Y-m-d H:i'));
        static::assertSame(EventStatus::Published, $event->getStatus());
        static::assertSame(EventType::cases()[0], $event->getType());
        static::assertTrue($event->isFeatured());
        static::assertSame($venue, $event->getLocation());
        static::assertSame($series, $event->getSeries());
        static::assertSame($host, $event->getUser());
        static::assertFalse($event->isInitial());
        static::assertSame(3, $event->getExternalRsvp());
        static::assertSame([$eventHost], $event->getHost()->toArray());
        static::assertSame($event, $context->resolveRef(Event::class, 1));

        $translation = $this->onlyPersisted(EventTranslation::class);
        static::assertSame('Go night', $translation->getTitle());
        static::assertSame('Bring a board', $translation->getDescription());
        static::assertSame('Every Monday', $translation->getTeaser());
        static::assertSame(1, $context->toSummary()->get('events', Outcome::Created));
    }

    public function testACreatorOutsideTheScopeIsCreditedToTheSteward(): void
    {
        // Arrange
        $creator = $this->withId(new User(), 5);
        $creator->setEmail('gone@example.org');
        $source = $this->withId(new Event(), 1);
        $source->setStart(new DateTime('2030-01-06 19:00'));
        $source->setUser($creator);

        // Act
        $rows = $this->section([$source])->export(new Scope(eventIds: [1], stewardEmail: 'owner@example.org'), $this->images());

        // Assert
        static::assertSame('owner@example.org', $rows[0]['creator_email']);
    }

    public function testEveryScopeEventIsExportedIncludingRecurringOccurrences(): void
    {
        // Arrange
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('findBy')->with(['id' => [1, 2]], ['id' => 'ASC'])->willReturn([]);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $section = new EventsSection($em, $this->createStub(LocationRepository::class), $this->createStub(UserRepository::class));

        // Act
        $rows = $section->export(new Scope(eventIds: [1, 2]), $this->images());

        // Assert
        static::assertSame([], $rows);
    }

    public function testALegacyRecurringRuleSynthesizesASeriesNamedAfterTheEnglishTitle(): void
    {
        // Arrange
        $row = ['start' => '2030-01-01 10:00', 'titles' => ['de' => 'Mein Alt-Event', 'en' => 'My Legacy Event'], 'recurring_rule' => 'Weekly'];

        // Act
        $this->section()->import([$row], $this->context());

        // Assert
        $series = $this->onlyPersisted(EventSeries::class);
        static::assertSame('My Legacy Event', $series->getName());
        static::assertSame(EventInterval::Weekly, $series->getRule());
        static::assertSame($series, $this->onlyPersisted(Event::class)->getSeries());
    }

    public function testAnEventWithoutSeriesDataStaysSeriesless(): void
    {
        // Arrange
        $row = ['start' => '2030-01-01 10:00', 'titles' => ['en' => 'One-Time Event']];

        // Act
        $this->section()->import([$row], $this->context());

        // Assert
        static::assertNull($this->onlyPersisted(Event::class)->getSeries());
        static::assertSame([], array_filter($this->persisted, static fn(object $entity): bool => $entity instanceof EventSeries));
    }

    public function testAnEventWithoutAKnownVenueOrCreatorFallsBackToAnUnknownVenueAndTheSystemUser(): void
    {
        // Arrange
        $context = $this->context();

        // Act
        $this->section()->import([['start' => '2030-01-01 10:00', 'location_ref' => 99, 'creator_email' => 'gone@example.org']], $context);

        // Assert
        $event = $this->onlyPersisted(Event::class);
        static::assertSame('Unknown', $event->getLocation()?->getName());
        static::assertSame($context->getSystemUser(), $event->getUser());
    }

    /**
     * @param list<Event> $events
     */
    private function section(array $events = []): EventsSection
    {
        return new EventsSection(
            $this->entityManager($events),
            $this->createStub(LocationRepository::class),
            $this->createStub(UserRepository::class),
        );
    }

    private function translation(string $language, string $title, string $description, string $teaser): EventTranslation
    {
        $translation = new EventTranslation();
        $translation->setLanguage($language);
        $translation->setTitle($title);
        $translation->setDescription($description);
        $translation->setTeaser($teaser);

        return $translation;
    }
}
