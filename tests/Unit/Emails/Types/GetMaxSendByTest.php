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
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\AppStateService;
use App\Service\Config\ConfigService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for each email type's getMaxSendBy() policy. The exact numbers come
 * from the Phase 3 analysis table in .claude/plans/2026-04-21_email-queue-max-delay-guard.md.
 * A sign flip or a changed constant should fail one of these assertions immediately.
 */
final class GetMaxSendByTest extends TestCase
{
    private const string NOW = '2026-04-21 10:00:00';

    // =========================================================================
    // Event-bound: min(now + X, event.start)
    // =========================================================================

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function eventReminderProvider(): iterable
    {
        // budget = 3h, clock = 2026-04-21 10:00:00 -> now+3h = 2026-04-21 13:00:00
        yield 'event far in future uses 3h budget' => ['2026-04-21 18:00:00', '2026-04-21 13:00:00'];
        yield 'event soon (under 3h) clamps to event start' => ['2026-04-21 11:30:00', '2026-04-21 11:30:00'];
        yield 'event already started returns past cap (real incident path)' => ['2026-04-21 08:00:00', '2026-04-21 08:00:00'];
    }

    #[DataProvider('eventReminderProvider')]
    public function testEventReminderCap(string $eventStart, string $expected): void
    {
        $email = new EventReminderEmail(
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
            $this->createStub(EventRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
        $result = $email->getMaxSendBy(
            ['event' => $this->eventStartingAt($eventStart)],
            new DateTimeImmutable(self::NOW),
        );

        static::assertSame($expected, $result?->format('Y-m-d H:i:s'));
    }

    public function testEventReminderCapReturnsNullWhenContextMissesEvent(): void
    {
        $email = new EventReminderEmail(
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
            $this->createStub(EventRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );

        static::assertNull($email->getMaxSendBy([], new DateTimeImmutable(self::NOW)));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function cancellationProvider(): iterable
    {
        // budget = 6h -> now+6h = 2026-04-21 16:00:00
        yield 'event far in future uses 6h budget' => ['2026-04-22 10:00:00', '2026-04-21 16:00:00'];
        yield 'event within 6h clamps to event start' => ['2026-04-21 13:00:00', '2026-04-21 13:00:00'];
        yield 'event already started' => ['2026-04-21 09:00:00', '2026-04-21 09:00:00'];
    }

    #[DataProvider('cancellationProvider')]
    public function testNotificationEventCanceledCap(string $eventStart, string $expected): void
    {
        $email = new NotificationEventCanceledEmail(
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
        );
        $result = $email->getMaxSendBy(
            ['event' => $this->eventStartingAt($eventStart)],
            new DateTimeImmutable(self::NOW),
        );

        static::assertSame($expected, $result?->format('Y-m-d H:i:s'));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function rsvpAggregatedProvider(): iterable
    {
        // budget = 12h -> now+12h = 2026-04-21 22:00:00
        yield 'event far in future uses 12h budget' => ['2026-04-22 10:00:00', '2026-04-21 22:00:00'];
        yield 'event within 12h clamps to event start' => ['2026-04-21 19:00:00', '2026-04-21 19:00:00'];
        yield 'event already started' => ['2026-04-21 09:00:00', '2026-04-21 09:00:00'];
    }

    #[DataProvider('rsvpAggregatedProvider')]
    public function testRsvpAggregatedCap(string $eventStart, string $expected): void
    {
        $email = new RsvpAggregatedEmail(
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
            $this->createStub(EventRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
        $result = $email->getMaxSendBy(
            ['event' => $this->eventStartingAt($eventStart)],
            new DateTimeImmutable(self::NOW),
        );

        static::assertSame($expected, $result?->format('Y-m-d H:i:s'));
    }

    // =========================================================================
    // Time-windowed: now + X
    // =========================================================================

    public function testUpcomingDigestCapIsFourHours(): void
    {
        $email = new UpcomingDigestEmail(
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
            $this->createStub(EventRepository::class),
            $this->createStub(UserRepository::class),
            $this->createStub(AppStateService::class),
            [],
        );

        $now = new DateTimeImmutable(self::NOW);
        $result = $email->getMaxSendBy([], $now);

        static::assertSame('2026-04-21 14:00:00', $result?->format('Y-m-d H:i:s'));
    }

    public function testNotificationMessageCapIsSixHours(): void
    {
        $email = new NotificationMessageEmail(
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
        );

        $result = $email->getMaxSendBy([], new DateTimeImmutable(self::NOW));

        static::assertSame('2026-04-21 16:00:00', $result?->format('Y-m-d H:i:s'));
    }

    public function testAdminNotificationCapIsTwelveHours(): void
    {
        $email = new AdminNotificationEmail(
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
        );

        $result = $email->getMaxSendBy([], new DateTimeImmutable(self::NOW));

        static::assertSame('2026-04-21 22:00:00', $result?->format('Y-m-d H:i:s'));
    }

    public function testWelcomeCapIsTwelveHours(): void
    {
        $email = new WelcomeEmail(
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
        );

        $result = $email->getMaxSendBy([], new DateTimeImmutable(self::NOW));

        static::assertSame('2026-04-21 22:00:00', $result?->format('Y-m-d H:i:s'));
    }

    public function testAnnouncementCapIsTwentyFourHours(): void
    {
        $email = new AnnouncementEmail(
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
        );

        $result = $email->getMaxSendBy([], new DateTimeImmutable(self::NOW));

        static::assertSame('2026-04-22 10:00:00', $result?->format('Y-m-d H:i:s'));
    }

    // =========================================================================
    // No-cap (null) - locks the contract against accidental future caps
    // =========================================================================

    public function testSupportNotificationHasNoCap(): void
    {
        $email = new SupportNotificationEmail(
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
        );

        static::assertNull($email->getMaxSendBy([], new DateTimeImmutable(self::NOW)));
    }

    public function testPasswordResetHasNoCap(): void
    {
        $email = new PasswordResetEmail(
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
        );

        static::assertNull($email->getMaxSendBy([], new DateTimeImmutable(self::NOW)));
    }

    public function testVerificationRequestHasNoCap(): void
    {
        $email = new VerificationRequestEmail(
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
        );

        static::assertNull($email->getMaxSendBy([], new DateTimeImmutable(self::NOW)));
    }

    private function eventStartingAt(string $when): Event
    {
        // Must be mutable DateTime - production uses DateTimeImmutable::createFromMutable()
        $event = $this->createStub(Event::class);
        $event->method('getStart')->willReturn(new DateTime($when));

        return $event;
    }
}
