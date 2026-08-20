<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Types;

use App\Emails\EmailQueueInterface;
use App\Emails\Types\AdminNotificationEmail;
use App\Emails\Types\AnnouncementEmail;
use App\Emails\Types\EventReminderEmail;
use App\Emails\Types\EventUpdateNotificationEmail;
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
use App\Service\Support\RecipientResolver;
use App\Service\AppStateService;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use App\Service\Http\RequestHostResolver;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class GetMaxSendByTest extends TestCase
{
    private const string NOW = '2026-04-21 10:00:00';

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function eventReminderProvider(): iterable
    {
        yield 'event far in future uses 3h budget' => ['2026-04-21 18:00:00', '2026-04-21 13:00:00'];
        yield 'event soon (under 3h) clamps to event start' => ['2026-04-21 11:30:00', '2026-04-21 11:30:00'];
        yield 'event already started returns past cap (real incident path)' => [
            '2026-04-21 08:00:00',
            '2026-04-21 08:00:00',
        ];
    }

    #[DataProvider('eventReminderProvider')]
    public function testEventReminderCap(string $eventStart, string $expected): void
    {
        $email = new EventReminderEmail(
            $this->createStub(BlocklistCheckerInterface::class),
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
            $this->createStub(EventRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
        $result = $email->getMaxSendBy([
            'event' => $this->eventStartingAt($eventStart),
        ], new DateTimeImmutable(self::NOW));

        static::assertSame($expected, $result?->format('Y-m-d H:i:s'));
    }

    public function testEventReminderCapReturnsNullWhenContextMissesEvent(): void
    {
        $email = new EventReminderEmail(
            $this->createStub(BlocklistCheckerInterface::class),
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
        yield 'event far in future uses 6h budget' => ['2026-04-22 10:00:00', '2026-04-21 16:00:00'];
        yield 'event within 6h clamps to event start' => ['2026-04-21 13:00:00', '2026-04-21 13:00:00'];
        yield 'event already started' => ['2026-04-21 09:00:00', '2026-04-21 09:00:00'];
    }

    #[DataProvider('cancellationProvider')]
    public function testNotificationEventCanceledCap(string $eventStart, string $expected): void
    {
        $email = new NotificationEventCanceledEmail(
            $this->createStub(BlocklistCheckerInterface::class),
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
            $this->createStub(RequestHostResolver::class),
        );
        $result = $email->getMaxSendBy([
            'event' => $this->eventStartingAt($eventStart),
        ], new DateTimeImmutable(self::NOW));

        static::assertSame($expected, $result?->format('Y-m-d H:i:s'));
    }

    #[DataProvider('cancellationProvider')]
    public function testEventUpdateNotificationCap(string $eventStart, string $expected): void
    {
        $email = new EventUpdateNotificationEmail(
            $this->createStub(BlocklistCheckerInterface::class),
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
            $this->createStub(TranslatorInterface::class),
            $this->createStub(RequestHostResolver::class),
        );
        $result = $email->getMaxSendBy([
            'event' => $this->eventStartingAt($eventStart),
        ], new DateTimeImmutable(self::NOW));

        static::assertSame($expected, $result?->format('Y-m-d H:i:s'));
    }

    public function testEventUpdateNotificationCapReturnsNullWhenContextMissesEvent(): void
    {
        $email = new EventUpdateNotificationEmail(
            $this->createStub(BlocklistCheckerInterface::class),
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
            $this->createStub(TranslatorInterface::class),
            $this->createStub(RequestHostResolver::class),
        );

        static::assertNull($email->getMaxSendBy([], new DateTimeImmutable(self::NOW)));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function rsvpAggregatedProvider(): iterable
    {
        yield 'event far in future uses 12h budget' => ['2026-04-22 10:00:00', '2026-04-21 22:00:00'];
        yield 'event within 12h clamps to event start' => ['2026-04-21 19:00:00', '2026-04-21 19:00:00'];
        yield 'event already started' => ['2026-04-21 09:00:00', '2026-04-21 09:00:00'];
    }

    #[DataProvider('rsvpAggregatedProvider')]
    public function testRsvpAggregatedCap(string $eventStart, string $expected): void
    {
        $email = new RsvpAggregatedEmail(
            $this->createStub(BlocklistCheckerInterface::class),
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
            $this->createStub(EventRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
        $result = $email->getMaxSendBy([
            'event' => $this->eventStartingAt($eventStart),
        ], new DateTimeImmutable(self::NOW));

        static::assertSame($expected, $result?->format('Y-m-d H:i:s'));
    }

    public function testUpcomingDigestCapIsFourHours(): void
    {
        $email = new UpcomingDigestEmail(
            $this->createStub(BlocklistCheckerInterface::class),
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
            $this->createStub(BlocklistCheckerInterface::class),
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
            new \Symfony\Component\Clock\MockClock(),
            $this->createStub(RequestHostResolver::class),
        );

        $result = $email->getMaxSendBy([], new DateTimeImmutable(self::NOW));

        static::assertSame('2026-04-21 16:00:00', $result?->format('Y-m-d H:i:s'));
    }

    public function testAdminNotificationCapIsTwelveHours(): void
    {
        $email = new AdminNotificationEmail(
            $this->createStub(BlocklistCheckerInterface::class),
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
        );

        $result = $email->getMaxSendBy([], new DateTimeImmutable(self::NOW));

        static::assertSame('2026-04-21 22:00:00', $result?->format('Y-m-d H:i:s'));
    }

    public function testWelcomeCapIsTwelveHours(): void
    {
        $email = new WelcomeEmail(
            $this->createStub(BlocklistCheckerInterface::class),
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
            $this->createStub(RequestHostResolver::class),
        );

        $result = $email->getMaxSendBy([], new DateTimeImmutable(self::NOW));

        static::assertSame('2026-04-21 22:00:00', $result?->format('Y-m-d H:i:s'));
    }

    public function testAnnouncementCapIsTwentyFourHours(): void
    {
        $email = new AnnouncementEmail(
            $this->createStub(BlocklistCheckerInterface::class),
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
            $this->createStub(RequestHostResolver::class),
        );

        $result = $email->getMaxSendBy([], new DateTimeImmutable(self::NOW));

        static::assertSame('2026-04-22 10:00:00', $result?->format('Y-m-d H:i:s'));
    }

    public function testSupportNotificationHasNoCap(): void
    {
        $email = new SupportNotificationEmail(
            $this->createStub(BlocklistCheckerInterface::class),
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
            $this->createStub(RecipientResolver::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(TranslatorInterface::class),
        );

        static::assertNull($email->getMaxSendBy([], new DateTimeImmutable(self::NOW)));
    }

    public function testPasswordResetHasNoCap(): void
    {
        $email = new PasswordResetEmail(
            $this->createStub(BlocklistCheckerInterface::class),
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
            $this->createStub(RequestHostResolver::class),
        );

        static::assertNull($email->getMaxSendBy([], new DateTimeImmutable(self::NOW)));
    }

    public function testVerificationRequestHasNoCap(): void
    {
        $email = new VerificationRequestEmail(
            $this->createStub(BlocklistCheckerInterface::class),
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
            $this->createStub(RequestHostResolver::class),
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
