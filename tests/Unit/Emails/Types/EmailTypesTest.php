<?php

declare(strict_types=1);

namespace Tests\Unit\Emails\Types;

use App\Emails\EmailInterface;
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
use App\Enum\EmailType;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\AppStateService;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EmailTypesTest extends TestCase
{
    private EmailQueueInterface $queue;
    private ConfigService $config;
    private BlocklistCheckerInterface $blocklist;

    protected function setUp(): void
    {
        $this->queue = $this->createStub(EmailQueueInterface::class);
        $this->config = $this->createStub(ConfigService::class);
        $this->blocklist = $this->createStub(BlocklistCheckerInterface::class);
    }

    private function allTypes(): array
    {
        $eventRepo = $this->createStub(EventRepository::class);
        $em = $this->createStub(EntityManagerInterface::class);

        return [
            'AdminNotification' => new AdminNotificationEmail($this->blocklist, $this->queue, $this->config),
            'Announcement' => new AnnouncementEmail($this->blocklist, $this->queue, $this->config),
            'EventReminder' => new EventReminderEmail($this->blocklist, $this->queue, $this->config, $eventRepo, $em),
            'NotificationEventCanceled' => new NotificationEventCanceledEmail($this->blocklist, $this->queue, $this->config),
            'NotificationMessage' => new NotificationMessageEmail($this->blocklist, $this->queue, $this->config, new \Symfony\Component\Clock\MockClock()),
            'PasswordReset' => new PasswordResetEmail($this->blocklist, $this->queue, $this->config),
            'RsvpAggregated' => new RsvpAggregatedEmail($this->blocklist, $this->queue, $this->config, $eventRepo, $em),
            'SupportNotification' => new SupportNotificationEmail($this->blocklist, $this->queue, $this->config),
            'UpcomingDigest' => new UpcomingDigestEmail(
                $this->blocklist,
                $this->queue,
                $this->config,
                $eventRepo,
                $this->createStub(UserRepository::class),
                $this->createStub(AppStateService::class),
                [],
            ),
            'VerificationRequest' => new VerificationRequestEmail($this->blocklist, $this->queue, $this->config),
            'Welcome' => new WelcomeEmail($this->blocklist, $this->queue, $this->config),
        ];
    }

    public static function emailTypeProvider(): iterable
    {
        // Keys must match what allTypes() returns; instances are built in the test body
        // because setUp() hasn't run yet when the provider executes.
        yield 'AdminNotification' => ['AdminNotification'];
        yield 'Announcement' => ['Announcement'];
        yield 'EventReminder' => ['EventReminder'];
        yield 'NotificationEventCanceled' => ['NotificationEventCanceled'];
        yield 'NotificationMessage' => ['NotificationMessage'];
        yield 'PasswordReset' => ['PasswordReset'];
        yield 'RsvpAggregated' => ['RsvpAggregated'];
        yield 'SupportNotification' => ['SupportNotification'];
        yield 'UpcomingDigest' => ['UpcomingDigest'];
        yield 'VerificationRequest' => ['VerificationRequest'];
        yield 'Welcome' => ['Welcome'];
    }

    #[DataProvider('emailTypeProvider')]
    public function testGetDisplayMockDataHasRequiredKeys(string $key): void
    {
        // Arrange
        $email = $this->allTypes()[$key];

        // Act
        $data = $email->getDisplayMockData();

        // Assert
        static::assertArrayHasKey('subject', $data, "{$key}: missing 'subject'");
        static::assertArrayHasKey('context', $data, "{$key}: missing 'context'");
        static::assertIsString($data['subject'], "{$key}: 'subject' must be a string");
        static::assertNotEmpty($data['subject'], "{$key}: 'subject' must not be empty");
        static::assertIsArray($data['context'], "{$key}: 'context' must be an array");
    }

    #[DataProvider('emailTypeProvider')]
    public function testGetIdentifierMatchesEmailTypeEnum(string $key): void
    {
        // Arrange
        $email = $this->allTypes()[$key];

        // Act
        $identifier = $email->getIdentifier();

        // Assert
        $validValues = array_map(static fn(EmailType $t) => $t->value, EmailType::cases());
        static::assertContains($identifier, $validValues, "{$key}: identifier '{$identifier}' not in EmailType enum");
    }

    #[DataProvider('emailTypeProvider')]
    public function testImplementsEmailInterface(string $key): void
    {
        static::assertInstanceOf(EmailInterface::class, $this->allTypes()[$key]);
    }

    public function testAdminNotificationGuardCheckThrowsOnEmptyContext(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AdminNotificationEmail($this->blocklist, $this->queue, $this->config)->guardCheck([]);
    }

    public function testNotificationEventCanceledGuardCheckThrowsOnEmptyContext(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NotificationEventCanceledEmail($this->blocklist, $this->queue, $this->config)->guardCheck([]);
    }

    public function testPasswordResetGuardCheckThrowsOnEmptyContext(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PasswordResetEmail($this->blocklist, $this->queue, $this->config)->guardCheck([]);
    }

    public function testSupportNotificationGuardCheckThrowsOnEmptyContext(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SupportNotificationEmail($this->blocklist, $this->queue, $this->config)->guardCheck([]);
    }

    public function testVerificationRequestGuardCheckThrowsOnEmptyContext(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new VerificationRequestEmail($this->blocklist, $this->queue, $this->config)->guardCheck([]);
    }

    public function testWelcomeGuardCheckThrowsOnEmptyContext(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WelcomeEmail($this->blocklist, $this->queue, $this->config)->guardCheck([]);
    }
}
