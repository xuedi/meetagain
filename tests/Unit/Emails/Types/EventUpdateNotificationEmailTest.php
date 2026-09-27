<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Types;

use App\Emails\Types\EventUpdateNotificationEmail;
use App\Entity\Event;
use App\Entity\Location;
use App\Entity\NotificationSettings;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Http\RequestHostResolver;
use DateTime;
use DateTimeImmutable;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\MailerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Unit\Emails\SampleFactoryTrait;

class EventUpdateNotificationEmailTest extends TestCase
{
    use SampleFactoryTrait;

    private ConfigService $config;
    private BlocklistInterface $blocklist;
    private TranslatorInterface $translator;
    private RequestHostResolver $host;

    protected function setUp(): void
    {
        $this->config = $this->createStub(ConfigService::class);
        $this->config->method('getMailerAddress')->willReturn(new Address('noreply@example.com'));
        $this->config->method('getHost')->willReturn('https://example.com');

        $this->blocklist = $this->createStub(BlocklistInterface::class);

        $this->translator = $this->createStub(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnCallback(static fn(string $id): string => $id);

        $this->host = $this->createStub(RequestHostResolver::class);
        $this->host->method('getSchemeAndHost')->willReturn('https://example.com');
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    #[DataProvider('provideChanges')]
    public function testTheMessageNamesWhatChanged(array $before, array $after, string $expectedLine): void
    {
        // Arrange
        $email = $this->email();

        // Act
        $messages = $email->compose(['user' => $this->makeUser(), 'event' => $this->makeEvent(), 'before' => $before, 'after' => $after]);

        // Assert
        static::assertCount(1, $messages);
        static::assertStringContainsString($expectedLine, $messages[0]->getContext()['changesHtml']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>, string}>
     */
    public static function provideChanges(): iterable
    {
        yield 'a new start time' => [self::snapshot(start: 1_700_000_000), self::snapshot(start: 1_700_000_999), 'email_event_update.line_start'];
        yield 'a new location' => [
            self::snapshot(locationId: 7, locationName: 'Old Hall'),
            self::snapshot(locationId: 8, locationName: 'New Hall'),
            'email_event_update.line_location',
        ];
        yield 'a cancellation' => [self::snapshot(canceled: false), self::snapshot(canceled: true), 'email_event_update.line_canceled'];
        yield 'an event back on' => [self::snapshot(canceled: true), self::snapshot(canceled: false), 'email_event_update.line_uncanceled'];
    }

    public function testAChangedStartDoesNotMentionTheUnchangedLocation(): void
    {
        // Arrange
        $email = $this->email();

        // Act
        $messages = $email->compose([
            'user' => $this->makeUser(),
            'event' => $this->makeEvent(),
            'before' => self::snapshot(start: 1_700_000_000, locationId: 7),
            'after' => self::snapshot(start: 1_700_000_999, locationId: 7),
        ]);

        // Assert
        static::assertStringNotContainsString('email_event_update.line_location', $messages[0]->getContext()['changesHtml']);
    }

    public function testNothingIsComposedWhenTheSnapshotsAreEqual(): void
    {
        // Arrange
        $email = $this->email();

        // Act
        $messages = $email->compose(['user' => $this->makeUser(), 'event' => $this->makeEvent(), 'before' => self::snapshot(), 'after' => self::snapshot()]);

        // Assert
        static::assertSame([], $messages);
    }

    public function testGuardCheckReturnsFalseWhenAttendedEventUpdateOff(): void
    {
        // Arrange
        $email = new EventUpdateNotificationEmail(
            $this->blocklist,
            $this->mockSampleFactory(),
            $this->createStub(MailerInterface::class),
            $this->config,
            $this->translator,
            $this->host,
        );
        $user = $this->makeUser(settings: new NotificationSettings(['attendedEventUpdate' => false]));

        // Act & Assert
        static::assertFalse($email->guardCheck([
            'user' => $user,
            'event' => $this->makeEvent(),
        ]));
    }

    public function testGuardCheckReturnsTrueWhenAllPass(): void
    {
        // Arrange
        $email = new EventUpdateNotificationEmail(
            $this->blocklist,
            $this->mockSampleFactory(),
            $this->createStub(MailerInterface::class),
            $this->config,
            $this->translator,
            $this->host,
        );
        $user = $this->makeUser(settings: new NotificationSettings(['attendedEventUpdate' => true]));

        // Act & Assert
        static::assertTrue($email->guardCheck([
            'user' => $user,
            'event' => $this->makeEvent(),
        ]));
    }

    public function testGetIdentifierMatchesEnumValue(): void
    {
        $email = new EventUpdateNotificationEmail(
            $this->blocklist,
            $this->mockSampleFactory(),
            $this->createStub(MailerInterface::class),
            $this->config,
            $this->translator,
            $this->host,
        );

        static::assertSame(EmailType::EventUpdateNotification->value, $email->getIdentifier());
    }

    /**
     * @return array{start: int, startFormatted: string, locationId: ?int, locationName: string, canceled: bool}
     */
    private function email(): EventUpdateNotificationEmail
    {
        return new EventUpdateNotificationEmail(
            $this->blocklist,
            $this->mockSampleFactory(),
            $this->createStub(MailerInterface::class),
            $this->config,
            $this->translator,
            $this->host,
        );
    }

    private static function snapshot(int $start = 1_700_000_000, ?int $locationId = 7, string $locationName = 'Main Hall', bool $canceled = false): array
    {
        return [
            'start' => $start,
            'startFormatted' => new DateTimeImmutable()
                ->setTimestamp($start)
                ->format('Y-m-d H:i'),
            'locationId' => $locationId,
            'locationName' => $locationName,
            'canceled' => $canceled,
        ];
    }

    private function makeUser(
        string $email = 'user@example.com',
        string $name = 'Alice',
        string $locale = 'en',
        ?NotificationSettings $settings = null,
        bool $isNotification = true,
        int $id = 1,
    ): User {
        $user = $this->createStub(User::class);
        $user->method('getEmail')->willReturn($email);
        $user->method('getName')->willReturn($name);
        $user->method('getLocale')->willReturn($locale);
        $user->method('getId')->willReturn($id);
        $user->method('isNotification')->willReturn($isNotification);
        $user->method('getNotificationSettings')->willReturn($settings ?? new NotificationSettings([]));

        return $user;
    }

    private function makeEvent(): Event
    {
        $location = $this->createStub(Location::class);
        $location->method('getName')->willReturn('Main Hall');

        $event = $this->createStub(Event::class);
        $event->method('getStart')->willReturn(new DateTime('2026-06-01 19:00:00'));
        $event->method('getLocation')->willReturn($location);
        $event->method('getTitle')->willReturn('Test Event');
        $event->method('getId')->willReturn(42);

        return $event;
    }
}
