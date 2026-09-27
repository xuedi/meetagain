<?php declare(strict_types=1);

namespace Tests\Unit\Service\Event;

use App\Emails\Types\EventUpdateNotificationEmail;
use App\Entity\Event;
use App\Entity\NotificationSettings;
use App\Entity\User;
use App\Service\Event\AttendeeUpdateNotifier;
use DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class AttendeeUpdateNotifierTest extends TestCase
{
    public function testAnUnchangedSnapshotSendsNothing(): void
    {
        // Arrange
        $email = $this->email();
        $email->expects(self::never())->method('send');
        $notifier = new AttendeeUpdateNotifier($email);
        $event = $this->event(attendees: [$this->user(2)]);
        $snapshot = $notifier->snapshot($event);

        // Act
        $notifier->notify($event, null, $snapshot, $snapshot);
    }

    public function testAPastEventSendsNothing(): void
    {
        // Arrange
        $email = $this->email();
        $email->expects(self::never())->method('send');
        $notifier = new AttendeeUpdateNotifier($email);
        $event = $this->event(attendees: [$this->user(2)], start: '-1 day');

        // Act
        $notifier->notify($event, null, $this->before(), $this->after());

        // Assert
        self::assertSame(0, $notifier->countNotifiable($event));
    }

    public function testTheEditorIsSkipped(): void
    {
        // Arrange
        $editor = $this->user(2);
        $email = $this->email();
        $email->expects(self::never())->method('send');
        $notifier = new AttendeeUpdateNotifier($email);
        $event = $this->event(attendees: [$editor]);

        // Act
        $notifier->notify($event, $editor, $this->before(), $this->after());
    }

    public function testTheCreatorIsSkipped(): void
    {
        // Arrange
        $creator = $this->user(2);
        $email = $this->email();
        $email->expects(self::never())->method('send');
        $notifier = new AttendeeUpdateNotifier($email);
        $event = $this->event(attendees: [$creator], creator: $creator);

        // Act
        $notifier->notify($event, null, $this->before(), $this->after());
    }

    public function testAnAttendeeWhoOptedOutIsSkipped(): void
    {
        // Arrange
        $email = $this->email();
        $email->expects(self::never())->method('send');
        $notifier = new AttendeeUpdateNotifier($email);
        $event = $this->event(attendees: [$this->user(2, optedIn: false)]);

        // Act
        $notifier->notify($event, null, $this->before(), $this->after());

        // Assert
        self::assertSame(0, $notifier->countNotifiable($event));
    }

    public function testEveryRemainingAttendeeIsMailed(): void
    {
        // Arrange
        $email = $this->email();
        $email->expects(self::exactly(2))->method('send');
        $notifier = new AttendeeUpdateNotifier($email);
        $event = $this->event(attendees: [$this->user(2), $this->user(3), $this->user(4, optedIn: false)]);

        // Act
        $notifier->notify($event, null, $this->before(), $this->after());

        // Assert
        self::assertSame(2, $notifier->countNotifiable($event));
    }

    private function email(): MockObject&EventUpdateNotificationEmail
    {
        $email = $this->createMock(EventUpdateNotificationEmail::class);
        $email
            ->method('guardCheck')
            ->willReturnCallback(static fn(array $context): bool => $context['user']->getNotificationSettings()->isActive('attendedEventUpdate'));

        return $email;
    }

    /** @return array{start: int, startFormatted: string, locationId: ?int, locationName: string, canceled: bool} */
    private function before(): array
    {
        return ['start' => 100, 'startFormatted' => '2026-09-06 18:00', 'locationId' => 1, 'locationName' => 'Old', 'canceled' => false];
    }

    /** @return array{start: int, startFormatted: string, locationId: ?int, locationName: string, canceled: bool} */
    private function after(): array
    {
        return ['start' => 100, 'startFormatted' => '2026-09-06 18:00', 'locationId' => 2, 'locationName' => 'New', 'canceled' => false];
    }

    /** @param list<User> $attendees */
    private function event(array $attendees, ?User $creator = null, string $start = '+7 days'): Event
    {
        $event = new Event();
        $event->setStart(new DateTime($start));
        $event->setUser($creator);
        foreach ($attendees as $attendee) {
            $event->addRsvp($attendee);
        }

        return $event;
    }

    private function user(int $id, bool $optedIn = true): User
    {
        $user = new User();
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        $user->setNotificationSettings(new NotificationSettings(['attendedEventUpdate' => $optedIn]));

        return $user;
    }
}
