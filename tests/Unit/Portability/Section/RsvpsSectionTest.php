<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Section;

use App\Entity\Event;
use App\Entity\RsvpGuest;
use App\Entity\User;
use App\Portability\DataCategory;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\Section\RsvpsSection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class RsvpsSectionTest extends SectionTestCase
{
    public function testAnRsvpWithItsGuestsSurvivesTheRoundTrip(): void
    {
        // Arrange
        $member = $this->user(5, 'anna@example.org');
        $event = $this->eventWith(1, $member);
        $guest = new RsvpGuest($event, $member);
        $guest->increment();
        $guest->increment();
        $scope = new Scope(users: [5 => 'user'], eventIds: [1], grants: [5 => [DataCategory::Attendance]]);
        $exported = $this->section([$event], [$guest])->export($scope, $this->images());

        $context = $this->context();
        $target = new Event();
        $importedMember = new User();
        $context->mapRef(Event::class, 1, $target);
        $context->mapRef(User::class, 'anna@example.org', $importedMember);

        // Act
        $this->section()->import($exported, $context);

        // Assert
        static::assertTrue($target->hasRsvp($importedMember));
        $imported = $this->onlyPersisted(RsvpGuest::class);
        static::assertSame($target, $imported->getEvent());
        static::assertSame(2, $imported->getGuests());
        static::assertSame(1, $context->toSummary()->get('rsvps', Outcome::Created));
    }

    public function testOnlyMembersWhoShareTheirAttendanceAreExported(): void
    {
        // Arrange
        $event = $this->eventWith(1, $this->user(5, 'anna@example.org'), $this->user(6, 'bob@example.org'), $this->user(7, 'carl@example.org'));
        $scope = new Scope(users: [5 => 'user', 6 => 'user'], eventIds: [1], grants: [5 => [DataCategory::Attendance], 6 => [DataCategory::Interactions]]);

        // Act
        $rows = $this->section([$event])->export($scope, $this->images());

        // Assert
        static::assertSame([['event_ref' => 1, 'email' => 'anna@example.org', 'guests' => 0]], $rows);
    }

    public function testRowsAreOrderedByEventThenEmail(): void
    {
        // Arrange
        $zoe = $this->user(5, 'zoe@example.org');
        $anna = $this->user(6, 'anna@example.org');
        $scope = new Scope(users: [5 => 'user', 6 => 'user'], eventIds: [1, 2], grants: [5 => [DataCategory::Attendance], 6 => [DataCategory::Attendance]]);

        // Act
        $rows = $this->section([$this->eventWith(2, $zoe), $this->eventWith(1, $zoe, $anna)])->export($scope, $this->images());

        // Assert
        static::assertSame(
            [[1, 'anna@example.org'], [1, 'zoe@example.org'], [2, 'zoe@example.org']],
            array_map(static fn(array $row): array => [$row['event_ref'], $row['email']], $rows),
        );
    }

    public function testAnRsvpForAnUnknownEventOrMemberIsDropped(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapRef(Event::class, 1, new Event());
        $context->mapRef(User::class, 'anna@example.org', new User());

        // Act
        $this->section()->import([
            ['event_ref' => 99, 'email' => 'anna@example.org', 'guests' => 0],
            ['event_ref' => 1, 'email' => 'ghost@example.org', 'guests' => 0],
        ], $context);

        // Assert
        static::assertSame(2, $context->toSummary()->get('rsvps', Outcome::Dropped));
        static::assertSame(0, $context->toSummary()->get('rsvps', Outcome::Created));
    }

    public function testGuestsAreCappedAtTheMaximum(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapRef(Event::class, 1, new Event());
        $context->mapRef(User::class, 'anna@example.org', new User());

        // Act
        $this->section()->import([['event_ref' => 1, 'email' => 'anna@example.org', 'guests' => 99]], $context);

        // Assert
        static::assertSame(RsvpGuest::MAX_GUESTS, $this->onlyPersisted(RsvpGuest::class)->getGuests());
    }

    /**
     * @param list<Event> $events
     * @param list<RsvpGuest> $guests
     */
    private function section(array $events = [], array $guests = []): RsvpsSection
    {
        $eventRepository = $this->createStub(EntityRepository::class);
        $eventRepository->method('findBy')->willReturn($events);
        $guestRepository = $this->createStub(EntityRepository::class);
        $guestRepository->method('findBy')->willReturn($guests);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            static fn(string $class): EntityRepository => $class === RsvpGuest::class ? $guestRepository : $eventRepository,
        );
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        return new RsvpsSection($em);
    }

    private function eventWith(int $id, User ...$members): Event
    {
        $event = $this->withId(new Event(), $id);
        foreach ($members as $member) {
            $event->addRsvp($member);
        }

        return $event;
    }

    private function user(int $id, string $email): User
    {
        $user = $this->withId(new User(), $id);
        $user->setEmail($email);

        return $user;
    }
}
