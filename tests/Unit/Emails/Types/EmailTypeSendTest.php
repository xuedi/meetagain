<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Types;

use App\Emails\EmailQueueInterface;
use App\Emails\Types\AdminNotificationEmail;
use App\Emails\Types\AnnouncementEmail;
use App\Emails\Types\EventReminderEmail;
use App\Emails\Types\NotificationEventCanceledEmail;
use App\Emails\Types\NotificationMessageEmail;
use App\Emails\Types\PasswordResetEmail;
use App\Emails\Types\RsvpAggregatedEmail;
use App\Emails\Types\SupportNotificationEmail;
use App\Emails\Types\UpcomingDigestEmail;
use App\Emails\Types\VerificationRequestEmail;
use App\Emails\Types\WelcomeEmail;
use App\Entity\Event;
use App\Entity\Location;
use App\Entity\NotificationSettings;
use App\Entity\SupportRequest;
use App\Entity\User;
use App\Enum\ContactType;
use App\Enum\EmailType;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\AppStateService;
use App\Service\Config\ConfigService;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;

class EmailTypeSendTest extends TestCase
{
    private ConfigService $config;

    protected function setUp(): void
    {
        $this->config = $this->createStub(ConfigService::class);
        $this->config->method('getMailerAddress')->willReturn(new Address('noreply@example.com'));
        $this->config->method('getHost')->willReturn('https://example.com');
        $this->config->method('getUrl')->willReturn('https://example.com');
    }

    private function makeUser(
        string $email = 'user@example.com',
        string $name = 'Alice',
        string $locale = 'en',
        ?NotificationSettings $settings = null,
        bool $isNotification = true,
        ?DateTimeInterface $lastLogin = null,
        int $id = 1,
    ): User {
        $user = $this->createStub(User::class);
        $user->method('getEmail')->willReturn($email);
        $user->method('getName')->willReturn($name);
        $user->method('getLocale')->willReturn($locale);
        $user->method('getId')->willReturn($id);
        $user->method('getRegcode')->willReturn('TOKEN123');
        $user->method('isNotification')->willReturn($isNotification);
        $user->method('getLastLogin')->willReturn($lastLogin ?? new DateTime('-24 hours'));
        $user->method('getNotificationSettings')->willReturn($settings ?? new NotificationSettings([]));

        return $user;
    }

    private function makeEvent(bool $hasRsvp = false): Event
    {
        $location = $this->createStub(Location::class);
        $location->method('getName')->willReturn('Main Hall');

        $event = $this->createStub(Event::class);
        $event->method('getStart')->willReturn(new DateTimeImmutable('2026-06-01 19:00:00'));
        $event->method('getLocation')->willReturn($location);
        $event->method('getTitle')->willReturn('Test Event');
        $event->method('getId')->willReturn(42);
        $event->method('hasRsvp')->willReturn($hasRsvp);

        return $event;
    }

    // =========================================================================
    // send() — assert queue->enqueue() fires with the correct EmailType
    // =========================================================================

    public function testAdminNotificationSend(): void
    {
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->once())->method('enqueue')
            ->with($this->anything(), $this->anything(), EmailType::AdminNotification, $this->anything());

        (new AdminNotificationEmail($queue, $this->config))->send([
            'user' => $this->makeUser(),
            'sectionsHtml' => '<p>pending</p>',
        ]);
    }

    public function testAnnouncementSend(): void
    {
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->once())->method('enqueue')
            ->with($this->anything(), $this->anything(), EmailType::Announcement, $this->anything(), false);

        (new AnnouncementEmail($queue, $this->config))->send([
            'user' => $this->makeUser(),
            'renderedContent' => ['title' => 'Hello', 'content' => '<p>body</p>'],
            'announcementUrl' => 'https://example.com/announcement/1',
        ]);
    }

    public function testNotificationEventCanceledSend(): void
    {
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->once())->method('enqueue')
            ->with($this->anything(), $this->anything(), EmailType::NotificationEventCanceled, $this->anything());

        (new NotificationEventCanceledEmail($queue, $this->config))->send([
            'user' => $this->makeUser(),
            'event' => $this->makeEvent(),
        ]);
    }

    public function testNotificationMessageSend(): void
    {
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->once())->method('enqueue')
            ->with($this->anything(), $this->anything(), EmailType::NotificationMessage, $this->anything());

        $sender = $this->makeUser('sender@example.com', 'Bob', 'en', null, true, null, 2);

        (new NotificationMessageEmail($queue, $this->config))->send([
            'sender' => $sender,
            'recipient' => $this->makeUser(),
        ]);
    }

    public function testPasswordResetSend(): void
    {
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->once())->method('enqueue')
            ->with($this->anything(), $this->anything(), EmailType::PasswordResetRequest, $this->anything());

        (new PasswordResetEmail($queue, $this->config))->send([
            'user' => $this->makeUser(),
        ]);
    }

    public function testSupportNotificationSend(): void
    {
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->once())->method('enqueue')
            ->with($this->anything(), $this->anything(), EmailType::SupportNotification, $this->anything());

        $request = $this->createStub(SupportRequest::class);
        $request->method('getContactType')->willReturn(ContactType::General);
        $request->method('getName')->willReturn('John');
        $request->method('getEmail')->willReturn('john@example.com');
        $request->method('getMessage')->willReturn('Help!');
        $request->method('getCreatedAt')->willReturn(new DateTimeImmutable('2026-01-01'));

        (new SupportNotificationEmail($queue, $this->config))->send([
            'request' => $request,
        ]);
    }

    public function testVerificationRequestSend(): void
    {
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->once())->method('enqueue')
            ->with($this->anything(), $this->anything(), EmailType::VerificationRequest, $this->anything());

        (new VerificationRequestEmail($queue, $this->config))->send([
            'user' => $this->makeUser(),
        ]);
    }

    public function testWelcomeSend(): void
    {
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->once())->method('enqueue')
            ->with($this->anything(), $this->anything(), EmailType::Welcome, $this->anything());

        (new WelcomeEmail($queue, $this->config))->send([
            'user' => $this->makeUser(),
        ]);
    }

    public function testEventReminderSend(): void
    {
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->once())->method('enqueue')
            ->with($this->anything(), $this->anything(), EmailType::EventReminder, $this->anything());

        (new EventReminderEmail(
            $queue,
            $this->config,
            $this->createStub(EventRepository::class),
            $this->createStub(EntityManagerInterface::class),
        ))->send([
            'user' => $this->makeUser(),
            'event' => $this->makeEvent(),
        ]);
    }

    public function testRsvpAggregatedSendEnqueuesWhenAttendeesPresent(): void
    {
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->once())->method('enqueue')
            ->with($this->anything(), $this->anything(), EmailType::NotificationRsvpAggregated, $this->anything());

        $recipient = $this->makeUser(id: 5);
        $attendee = $this->makeUser('a@a.com', 'Eve', 'en', null, true, null, 6);

        (new RsvpAggregatedEmail(
            $queue,
            $this->config,
            $this->createStub(EventRepository::class),
            $this->createStub(EntityManagerInterface::class),
        ))->send([
            'user' => $recipient,
            'event' => $this->makeEvent(),
            'attendeeMap' => [5 => ['attendees' => [$attendee]]],
        ]);
    }

    public function testRsvpAggregatedSendSkipsWhenNoAttendees(): void
    {
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->never())->method('enqueue');

        (new RsvpAggregatedEmail(
            $queue,
            $this->config,
            $this->createStub(EventRepository::class),
            $this->createStub(EntityManagerInterface::class),
        ))->send([
            'user' => $this->makeUser(id: 5),
            'event' => $this->makeEvent(),
            'attendeeMap' => [],
        ]);
    }

    // =========================================================================
    // guardCheck() — complex branches
    // =========================================================================

    public function testAnnouncementGuardCheckReturnsTrueWhenActive(): void
    {
        $user = $this->makeUser(settings: new NotificationSettings(['announcements' => true]));

        static::assertTrue(
            (new AnnouncementEmail($this->createStub(EmailQueueInterface::class), $this->config))
                ->guardCheck(['user' => $user])
        );
    }

    public function testAnnouncementGuardCheckReturnsFalseWhenInactive(): void
    {
        $user = $this->makeUser(settings: new NotificationSettings(['announcements' => false]));

        static::assertFalse(
            (new AnnouncementEmail($this->createStub(EmailQueueInterface::class), $this->config))
                ->guardCheck(['user' => $user])
        );
    }

    public function testNotificationMessageGuardCheckReturnsFalseWhenNotificationsOff(): void
    {
        $user = $this->makeUser(isNotification: false);

        static::assertFalse(
            (new NotificationMessageEmail($this->createStub(EmailQueueInterface::class), $this->config))
                ->guardCheck(['recipient' => $user])
        );
    }

    public function testNotificationMessageGuardCheckReturnsFalseWhenReceivedMessageOff(): void
    {
        $user = $this->makeUser(settings: new NotificationSettings(['receivedMessage' => false]));

        static::assertFalse(
            (new NotificationMessageEmail($this->createStub(EmailQueueInterface::class), $this->config))
                ->guardCheck(['recipient' => $user])
        );
    }

    public function testNotificationMessageGuardCheckReturnsFalseWhenRecentLogin(): void
    {
        $user = $this->makeUser(
            settings: new NotificationSettings(['receivedMessage' => true]),
            lastLogin: new DateTime('-10 minutes'),
        );

        static::assertFalse(
            (new NotificationMessageEmail($this->createStub(EmailQueueInterface::class), $this->config))
                ->guardCheck(['recipient' => $user])
        );
    }

    public function testNotificationMessageGuardCheckReturnsTrueWhenAllPass(): void
    {
        $user = $this->makeUser(
            settings: new NotificationSettings(['receivedMessage' => true]),
            lastLogin: new DateTime('-3 hours'),
        );

        static::assertTrue(
            (new NotificationMessageEmail($this->createStub(EmailQueueInterface::class), $this->config))
                ->guardCheck(['recipient' => $user])
        );
    }

    public function testEventReminderGuardCheckReturnsFalseForNonUserObject(): void
    {
        $email = new EventReminderEmail(
            $this->createStub(EmailQueueInterface::class), $this->config,
            $this->createStub(EventRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );

        static::assertFalse($email->guardCheck(['user' => 'not-a-user']));
    }

    public function testEventReminderGuardCheckReturnsFalseWhenNotificationsOff(): void
    {
        $email = new EventReminderEmail(
            $this->createStub(EmailQueueInterface::class), $this->config,
            $this->createStub(EventRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );

        static::assertFalse($email->guardCheck(['user' => $this->makeUser(isNotification: false)]));
    }

    public function testEventReminderGuardCheckReturnsFalseWhenReminderSettingOff(): void
    {
        $email = new EventReminderEmail(
            $this->createStub(EmailQueueInterface::class), $this->config,
            $this->createStub(EventRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
        $user = $this->makeUser(settings: new NotificationSettings(['eventReminder' => false]));

        static::assertFalse($email->guardCheck(['user' => $user]));
    }

    public function testEventReminderGuardCheckReturnsTrueWhenAllPass(): void
    {
        $email = new EventReminderEmail(
            $this->createStub(EmailQueueInterface::class), $this->config,
            $this->createStub(EventRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
        $user = $this->makeUser(settings: new NotificationSettings(['eventReminder' => true]));

        static::assertTrue($email->guardCheck(['user' => $user]));
    }

    public function testRsvpAggregatedGuardCheckReturnsFalseWhenNotificationsOff(): void
    {
        $email = new RsvpAggregatedEmail(
            $this->createStub(EmailQueueInterface::class), $this->config,
            $this->createStub(EventRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );

        static::assertFalse($email->guardCheck(['user' => $this->makeUser(isNotification: false), 'event' => $this->makeEvent()]));
    }

    public function testRsvpAggregatedGuardCheckReturnsFalseWhenFollowingUpdatesOff(): void
    {
        $email = new RsvpAggregatedEmail(
            $this->createStub(EmailQueueInterface::class), $this->config,
            $this->createStub(EventRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
        $user = $this->makeUser(settings: new NotificationSettings(['followingUpdates' => false]));

        static::assertFalse($email->guardCheck(['user' => $user, 'event' => $this->makeEvent()]));
    }

    public function testRsvpAggregatedGuardCheckReturnsFalseWhenUserAlreadyRsvpd(): void
    {
        $email = new RsvpAggregatedEmail(
            $this->createStub(EmailQueueInterface::class), $this->config,
            $this->createStub(EventRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
        $user = $this->makeUser(settings: new NotificationSettings(['followingUpdates' => true]));

        static::assertFalse($email->guardCheck(['user' => $user, 'event' => $this->makeEvent(hasRsvp: true)]));
    }

    public function testRsvpAggregatedGuardCheckReturnsTrueWhenAllPass(): void
    {
        $email = new RsvpAggregatedEmail(
            $this->createStub(EmailQueueInterface::class), $this->config,
            $this->createStub(EventRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
        $user = $this->makeUser(settings: new NotificationSettings(['followingUpdates' => true]));

        static::assertTrue($email->guardCheck(['user' => $user, 'event' => $this->makeEvent()]));
    }

    public function testUpcomingDigestGuardCheckReturnsTrueWhenSettingOn(): void
    {
        $email = new UpcomingDigestEmail(
            $this->createStub(EmailQueueInterface::class), $this->config,
            $this->createStub(EventRepository::class),
            $this->createStub(UserRepository::class),
            $this->createStub(AppStateService::class),
            [],
        );
        $user = $this->makeUser(settings: new NotificationSettings(['upcomingEvents' => true]));

        static::assertTrue($email->guardCheck(['user' => $user]));
    }

    public function testUpcomingDigestGuardCheckReturnsFalseWhenSettingOff(): void
    {
        $email = new UpcomingDigestEmail(
            $this->createStub(EmailQueueInterface::class), $this->config,
            $this->createStub(EventRepository::class),
            $this->createStub(UserRepository::class),
            $this->createStub(AppStateService::class),
            [],
        );
        $user = $this->makeUser(settings: new NotificationSettings(['upcomingEvents' => false]));

        static::assertFalse($email->guardCheck(['user' => $user]));
    }
}
