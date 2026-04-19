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
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EmailTypesTest extends TestCase
{
    private EmailQueueInterface $queue;
    private ConfigService $config;

    protected function setUp(): void
    {
        $this->queue = $this->createStub(EmailQueueInterface::class);
        $this->config = $this->createStub(ConfigService::class);
    }

    private function allTypes(): array
    {
        $eventRepo = $this->createStub(EventRepository::class);
        $em = $this->createStub(EntityManagerInterface::class);

        return [
            'AdminNotification' => new AdminNotificationEmail($this->queue, $this->config),
            'Announcement' => new AnnouncementEmail($this->queue, $this->config),
            'EventReminder' => new EventReminderEmail($this->queue, $this->config, $eventRepo, $em),
            'NotificationEventCanceled' => new NotificationEventCanceledEmail($this->queue, $this->config),
            'NotificationMessage' => new NotificationMessageEmail($this->queue, $this->config),
            'PasswordReset' => new PasswordResetEmail($this->queue, $this->config),
            'RsvpAggregated' => new RsvpAggregatedEmail($this->queue, $this->config, $eventRepo, $em),
            'SupportNotification' => new SupportNotificationEmail($this->queue, $this->config),
            'UpcomingDigest' => new UpcomingDigestEmail(
                $this->queue,
                $this->config,
                $eventRepo,
                $this->createStub(UserRepository::class),
                $this->createStub(AppStateService::class),
                [],
            ),
            'VerificationRequest' => new VerificationRequestEmail($this->queue, $this->config),
            'Welcome' => new WelcomeEmail($this->queue, $this->config),
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

    public function testAdminNotificationGuardCheckAlwaysTrue(): void
    {
        static::assertTrue(new AdminNotificationEmail($this->queue, $this->config)->guardCheck([]));
    }

    public function testNotificationEventCanceledGuardCheckAlwaysTrue(): void
    {
        static::assertTrue(new NotificationEventCanceledEmail($this->queue, $this->config)->guardCheck([]));
    }

    public function testPasswordResetGuardCheckAlwaysTrue(): void
    {
        static::assertTrue(new PasswordResetEmail($this->queue, $this->config)->guardCheck([]));
    }

    public function testSupportNotificationGuardCheckAlwaysTrue(): void
    {
        static::assertTrue(new SupportNotificationEmail($this->queue, $this->config)->guardCheck([]));
    }

    public function testVerificationRequestGuardCheckAlwaysTrue(): void
    {
        static::assertTrue(new VerificationRequestEmail($this->queue, $this->config)->guardCheck([]));
    }

    public function testWelcomeGuardCheckAlwaysTrue(): void
    {
        static::assertTrue(new WelcomeEmail($this->queue, $this->config)->guardCheck([]));
    }
}
