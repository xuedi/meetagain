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
use App\Entity\NotificationSettings;
use App\Entity\SupportRequest;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\AppStateService;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;

/**
 * Blocklist enforcement lives in guardCheck() for every email type. A blocked recipient
 * causes guardCheck() to return false - same semantics as any other opt-out.
 */
final class EmailTypeBlocklistTest extends TestCase
{
    private ConfigService $config;
    private BlocklistCheckerInterface $blockingChecker;

    protected function setUp(): void
    {
        $this->config = $this->createStub(ConfigService::class);
        $this->config->method('getMailerAddress')->willReturn(new Address('support@example.com'));
        $this->config->method('getHost')->willReturn('https://example.com');
        $this->config->method('getUrl')->willReturn('https://example.com');

        $this->blockingChecker = $this->createStub(BlocklistCheckerInterface::class);
        $this->blockingChecker->method('isBlocked')->willReturn(true);
    }

    public function testAdminNotificationSkipsBlockedRecipient(): void
    {
        $email = new AdminNotificationEmail(
            $this->blockingChecker,
            $this->createStub(EmailQueueInterface::class),
            $this->config,
        );

        static::assertFalse($email->guardCheck([
            'user' => $this->userWithEmail('blocked@example.com'),
            'sectionsHtml' => '<p>x</p>',
        ]));
    }

    public function testAnnouncementSkipsBlockedRecipient(): void
    {
        $email = new AnnouncementEmail(
            $this->blockingChecker,
            $this->createStub(EmailQueueInterface::class),
            $this->config,
        );

        static::assertFalse($email->guardCheck([
            'user' => $this->userWithEmail('blocked@example.com', settings: new NotificationSettings(['announcements' => true])),
            'renderedContent' => ['title' => 't', 'content' => 'c'],
            'announcementUrl' => 'https://example.com/a/1',
        ]));
    }

    public function testEventReminderSkipsBlockedRecipient(): void
    {
        $email = new EventReminderEmail(
            $this->blockingChecker,
            $this->createStub(EmailQueueInterface::class),
            $this->config,
            $this->createStub(EventRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );

        static::assertFalse($email->guardCheck([
            'user' => $this->userWithEmail('blocked@example.com', settings: new NotificationSettings(['eventReminder' => true])),
            'event' => $this->createStub(Event::class),
        ]));
    }

    public function testNotificationEventCanceledSkipsBlockedRecipient(): void
    {
        $email = new NotificationEventCanceledEmail(
            $this->blockingChecker,
            $this->createStub(EmailQueueInterface::class),
            $this->config,
        );

        static::assertFalse($email->guardCheck([
            'user' => $this->userWithEmail('blocked@example.com'),
            'event' => $this->createStub(Event::class),
        ]));
    }

    public function testNotificationMessageSkipsBlockedRecipient(): void
    {
        $email = new NotificationMessageEmail(
            $this->blockingChecker,
            $this->createStub(EmailQueueInterface::class),
            $this->config,
            new \Symfony\Component\Clock\MockClock(),
        );

        static::assertFalse($email->guardCheck([
            'recipient' => $this->userWithEmail('blocked@example.com'),
            'sender' => $this->userWithEmail('sender@example.com'),
        ]));
    }

    public function testPasswordResetSkipsBlockedRecipient(): void
    {
        $email = new PasswordResetEmail(
            $this->blockingChecker,
            $this->createStub(EmailQueueInterface::class),
            $this->config,
        );

        static::assertFalse($email->guardCheck([
            'user' => $this->userWithEmail('blocked@example.com'),
        ]));
    }

    public function testRsvpAggregatedSkipsBlockedRecipient(): void
    {
        $email = new RsvpAggregatedEmail(
            $this->blockingChecker,
            $this->createStub(EmailQueueInterface::class),
            $this->config,
            $this->createStub(EventRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );

        static::assertFalse($email->guardCheck([
            'user' => $this->userWithEmail('blocked@example.com', settings: new NotificationSettings(['followingUpdates' => true])),
            'event' => $this->createStub(Event::class),
            'attendeeMap' => [],
        ]));
    }

    public function testSupportNotificationSkipsWhenMailerAddressBlocked(): void
    {
        $email = new SupportNotificationEmail(
            $this->blockingChecker,
            $this->createStub(EmailQueueInterface::class),
            $this->config,
        );

        static::assertFalse($email->guardCheck([
            'request' => $this->createStub(SupportRequest::class),
        ]));
    }

    public function testUpcomingDigestSkipsBlockedRecipient(): void
    {
        $email = new UpcomingDigestEmail(
            $this->blockingChecker,
            $this->createStub(EmailQueueInterface::class),
            $this->config,
            $this->createStub(EventRepository::class),
            $this->createStub(UserRepository::class),
            $this->createStub(AppStateService::class),
            [],
        );

        static::assertFalse($email->guardCheck([
            'user' => $this->userWithEmail('blocked@example.com', settings: new NotificationSettings(['upcomingEvents' => true])),
            'weekStart' => new DateTimeImmutable('2026-01-01'),
            'weekEnd' => new DateTimeImmutable('2026-01-08'),
        ]));
    }

    public function testVerificationRequestSkipsBlockedRecipient(): void
    {
        $email = new VerificationRequestEmail(
            $this->blockingChecker,
            $this->createStub(EmailQueueInterface::class),
            $this->config,
        );

        static::assertFalse($email->guardCheck([
            'user' => $this->userWithEmail('blocked@example.com'),
        ]));
    }

    public function testWelcomeSkipsBlockedRecipient(): void
    {
        $email = new WelcomeEmail(
            $this->blockingChecker,
            $this->createStub(EmailQueueInterface::class),
            $this->config,
        );

        static::assertFalse($email->guardCheck([
            'user' => $this->userWithEmail('blocked@example.com'),
        ]));
    }

    private function userWithEmail(string $email, ?NotificationSettings $settings = null): User
    {
        $user = $this->createStub(User::class);
        $user->method('getEmail')->willReturn($email);
        $user->method('isNotification')->willReturn(true);
        $user->method('getLastLogin')->willReturn(new DateTime('-24 hours'));
        $user->method('getNotificationSettings')->willReturn($settings ?? new NotificationSettings(['receivedMessage' => true]));

        return $user;
    }
}
