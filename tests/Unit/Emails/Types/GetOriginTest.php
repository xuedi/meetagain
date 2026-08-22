<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Emails\MockSampleFactory;
use App\Emails\Types\EventReminderEmail;
use App\Emails\Types\EventUpdateNotificationEmail;
use App\Emails\Types\NotificationEventCanceledEmail;
use App\Emails\Types\NotificationMessageEmail;
use App\Emails\Types\PasswordResetEmail;
use App\Emails\Types\RsvpAggregatedEmail;
use App\Emails\Types\SeriesRescheduledEmail;
use App\Emails\Types\VerificationRequestEmail;
use App\Emails\Types\WelcomeEmail;
use App\Entity\Event;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Unit\Emails\SampleFactoryTrait;

class GetOriginTest extends TestCase
{
    use SampleFactoryTrait;

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function eventOriginTypes(): iterable
    {
        yield 'event reminder' => [EventReminderEmail::class, 'event'];
        yield 'rsvp aggregated' => [RsvpAggregatedEmail::class, 'event'];
        yield 'event canceled' => [NotificationEventCanceledEmail::class, 'event'];
        yield 'event updated' => [EventUpdateNotificationEmail::class, 'event'];
        yield 'series rescheduled' => [SeriesRescheduledEmail::class, 'event'];
    }

    #[DataProvider('eventOriginTypes')]
    public function testAnEventDrivenTypeReportsItsEvent(string $class, string $key): void
    {
        // Arrange
        $event = new Event();
        $emailType = $this->build($class);

        // Act & Assert
        static::assertSame($event, $emailType->getOrigin([$key => $event]));
        static::assertNull($emailType->getOrigin([]));
        static::assertNull($emailType->getOrigin([$key => 'not an event']));
    }

    public function testWelcomeReportsTheApprovedUserRatherThanWhoeverApprovedThem(): void
    {
        // Arrange
        $user = new User();
        $emailType = $this->build(WelcomeEmail::class);

        // Act & Assert
        static::assertSame($user, $emailType->getOrigin(['user' => $user]));
        static::assertNull($emailType->getOrigin([]));
    }

    public function testANotificationMessageReportsItsRecipientNotItsSender(): void
    {
        // Arrange
        $recipient = new User();
        $sender = new User();
        $emailType = $this->build(NotificationMessageEmail::class);

        // Act & Assert
        static::assertSame($recipient, $emailType->getOrigin(['recipient' => $recipient, 'sender' => $sender]));
        static::assertNull($emailType->getOrigin(['sender' => $sender]));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function requestTimeTypes(): iterable
    {
        yield 'verification request' => [VerificationRequestEmail::class];
        yield 'password reset' => [PasswordResetEmail::class];
    }

    #[DataProvider('requestTimeTypes')]
    public function testATypeSentBeforeAnyMembershipExistsClaimsNoOrigin(string $class): void
    {
        // Arrange
        $emailType = $this->build($class);

        // Act & Assert
        static::assertNull($emailType->getOrigin(['user' => new User()]));
    }

    private function build(string $class): EmailAbstract
    {
        $blocklist = $this->createStub(BlocklistCheckerInterface::class);
        $samples = $this->mockSampleFactory();
        $queue = $this->createStub(EmailQueueInterface::class);
        $config = $this->createStub(ConfigService::class);

        return match ($class) {
            EventReminderEmail::class => new EventReminderEmail(
                $blocklist,
                $samples,
                $queue,
                $config,
                $this->createStub(EventRepository::class),
                $this->createStub(EntityManagerInterface::class),
            ),
            default => $this->buildViaReflection($class, $blocklist, $samples, $queue, $config),
        };
    }

    private function buildViaReflection(
        string $class,
        BlocklistCheckerInterface $blocklist,
        MockSampleFactory $samples,
        EmailQueueInterface $queue,
        ConfigService $config,
    ): EmailAbstract {
        $arguments = [];
        foreach (new ReflectionClass($class)->getConstructor()?->getParameters() ?? [] as $parameter) {
            $type = (string) $parameter->getType();
            $arguments[] = match ($type) {
                BlocklistCheckerInterface::class => $blocklist,
                MockSampleFactory::class => $samples,
                EmailQueueInterface::class => $queue,
                ConfigService::class => $config,
                'iterable', 'array' => [],
                default => $this->createStub($type),
            };
        }

        return new $class(...$arguments);
    }
}
