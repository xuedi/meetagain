<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Section;

use App\Entity\Event;
use App\Entity\Location;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\Section\LocationsSection;
use App\Repository\LocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class LocationsSectionTest extends SectionTestCase
{
    public function testALocationSurvivesTheRoundTrip(): void
    {
        // Arrange
        $source = $this->withId(new Location(), 7);
        $source->setName('Cafe Central');
        $source->setCity('Berlin');
        $source->setLatitude('52.5200000');
        $source->setLongitude('13.4050000');
        $exported = $this->section([$this->eventAt($source)])->export(new Scope(eventIds: [1]), $this->images());
        $context = $this->context();

        // Act
        $this->section()->import($exported, $context);

        // Assert
        $location = $this->onlyPersisted(Location::class);
        static::assertSame('Cafe Central', $location->getName());
        static::assertSame('Berlin', $location->getCity());
        static::assertSame('52.5200000', $location->getLatitude());
        static::assertSame('13.4050000', $location->getLongitude());
        static::assertSame($location, $context->resolveRef(Location::class, 7));
        static::assertSame(1, $context->toSummary()->get('locations', Outcome::Created));
    }

    public function testTwoEventsAtOneVenueExportItOnce(): void
    {
        // Arrange
        $venue = $this->withId(new Location(), 7);
        $venue->setName('Cafe Central');

        // Act
        $rows = $this->section([$this->eventAt($venue), $this->eventAt($venue)])->export(new Scope(eventIds: [1, 2]), $this->images());

        // Assert
        static::assertCount(1, $rows);
    }

    public function testTheVenuesOfEveryScopeEventAreExportedIncludingRecurringOccurrences(): void
    {
        // Arrange
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('findBy')->with(['id' => [1, 2]], ['id' => 'ASC'])->willReturn([]);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        // Act
        $rows = new LocationsSection($em, $this->createStub(LocationRepository::class))->export(new Scope(eventIds: [1, 2]), $this->images());

        // Assert
        static::assertSame([], $rows);
    }

    public function testAKnownTitleMapsToTheExistingVenue(): void
    {
        // Arrange
        $existing = new Location();
        $context = $this->context();

        // Act
        $this->section([], $existing)->import([['ref' => 7, 'title' => 'Cafe Central']], $context);

        // Assert
        static::assertSame([], $this->persisted);
        static::assertSame($existing, $context->resolveRef(Location::class, 7));
        static::assertSame(0, $context->toSummary()->get('locations', Outcome::Created));
        static::assertSame(1, $context->toSummary()->get('locations', Outcome::Matched));
    }

    /**
     * @param list<Event> $events
     */
    private function section(array $events = [], ?Location $existing = null): LocationsSection
    {
        $repository = $this->createStub(LocationRepository::class);
        $repository->method('findOneBy')->willReturn($existing);

        return new LocationsSection($this->entityManager($events), $repository);
    }

    private function eventAt(Location $location): Event
    {
        $event = new Event();
        $event->setLocation($location);

        return $event;
    }
}
