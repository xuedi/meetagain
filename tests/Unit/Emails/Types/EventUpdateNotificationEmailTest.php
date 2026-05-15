<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Types;

use App\Emails\EmailQueueInterface;
use App\Emails\Types\EventUpdateNotificationEmail;
use App\Entity\Event;
use App\Entity\Location;
use App\Entity\NotificationSettings;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use App\Service\Http\RequestHostResolver;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventUpdateNotificationEmailTest extends TestCase
{
    private ConfigService $config;
    private BlocklistCheckerInterface $blocklist;
    private TranslatorInterface $translator;
    private RequestHostResolver $host;

    protected function setUp(): void
    {
        $this->config = $this->createStub(ConfigService::class);
        $this->config->method('getMailerAddress')->willReturn(new Address('noreply@example.com'));
        $this->config->method('getHost')->willReturn('https://example.com');

        $this->blocklist = $this->createStub(BlocklistCheckerInterface::class);

        $this->translator = $this->createStub(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        $this->host = $this->createStub(RequestHostResolver::class);
        $this->host->method('getSchemeAndHost')->willReturn('https://example.com');
    }

    public function testSendEnqueuesWhenStartChanged(): void
    {
        // Arrange
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->once())->method('enqueue')
            ->with(
                $this->anything(),
                $this->callback(static function (TemplatedEmail $email): bool {
                    $context = $email->getContext();
                    return str_contains($context['changesHtml'], 'email_event_update.line_start')
                        && !str_contains($context['changesHtml'], 'email_event_update.line_location');
                }),
                EmailType::EventUpdateNotification,
                $this->anything(),
            );

        $email = new EventUpdateNotificationEmail($this->blocklist, $queue, $this->config, $this->translator, $this->host);

        // Act
        $email->send([
            'user' => $this->makeUser(),
            'event' => $this->makeEvent(),
            'before' => $this->snapshot(start: 1_700_000_000, locationId: 7, canceled: false),
            'after' => $this->snapshot(start: 1_700_000_999, locationId: 7, canceled: false),
        ]);
    }

    public function testSendEnqueuesWhenLocationChanged(): void
    {
        // Arrange
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->once())->method('enqueue')
            ->with(
                $this->anything(),
                $this->callback(static fn(TemplatedEmail $email): bool => str_contains($email->getContext()['changesHtml'], 'email_event_update.line_location')),
                EmailType::EventUpdateNotification,
                $this->anything(),
            );

        $email = new EventUpdateNotificationEmail($this->blocklist, $queue, $this->config, $this->translator, $this->host);

        // Act
        $email->send([
            'user' => $this->makeUser(),
            'event' => $this->makeEvent(),
            'before' => $this->snapshot(locationId: 7, locationName: 'Old Hall'),
            'after' => $this->snapshot(locationId: 8, locationName: 'New Hall'),
        ]);
    }

    public function testSendEnqueuesWhenCanceledFlipped(): void
    {
        // Arrange
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->once())->method('enqueue')
            ->with(
                $this->anything(),
                $this->callback(static fn(TemplatedEmail $email): bool => str_contains($email->getContext()['changesHtml'], 'email_event_update.line_canceled')),
                EmailType::EventUpdateNotification,
                $this->anything(),
            );

        $email = new EventUpdateNotificationEmail($this->blocklist, $queue, $this->config, $this->translator, $this->host);

        // Act
        $email->send([
            'user' => $this->makeUser(),
            'event' => $this->makeEvent(),
            'before' => $this->snapshot(canceled: false),
            'after' => $this->snapshot(canceled: true),
        ]);
    }

    public function testSendEnqueuesWhenUncanceled(): void
    {
        // Arrange
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->once())->method('enqueue')
            ->with(
                $this->anything(),
                $this->callback(static fn(TemplatedEmail $email): bool => str_contains($email->getContext()['changesHtml'], 'email_event_update.line_uncanceled')),
                EmailType::EventUpdateNotification,
                $this->anything(),
            );

        $email = new EventUpdateNotificationEmail($this->blocklist, $queue, $this->config, $this->translator, $this->host);

        // Act
        $email->send([
            'user' => $this->makeUser(),
            'event' => $this->makeEvent(),
            'before' => $this->snapshot(canceled: true),
            'after' => $this->snapshot(canceled: false),
        ]);
    }

    public function testSendDoesNotEnqueueWhenSnapshotsAreEqual(): void
    {
        // Arrange
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->never())->method('enqueue');

        $email = new EventUpdateNotificationEmail($this->blocklist, $queue, $this->config, $this->translator, $this->host);

        // Act
        $email->send([
            'user' => $this->makeUser(),
            'event' => $this->makeEvent(),
            'before' => $this->snapshot(),
            'after' => $this->snapshot(),
        ]);
    }

    public function testGuardCheckReturnsFalseWhenAttendedEventUpdateOff(): void
    {
        // Arrange
        $email = new EventUpdateNotificationEmail(
            $this->blocklist,
            $this->createStub(EmailQueueInterface::class),
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
            $this->createStub(EmailQueueInterface::class),
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
            $this->createStub(EmailQueueInterface::class),
            $this->config,
            $this->translator,
            $this->host,
        );

        static::assertSame(EmailType::EventUpdateNotification->value, $email->getIdentifier());
    }

    /**
     * @return array{start: int, startFormatted: string, locationId: ?int, locationName: string, canceled: bool}
     */
    private function snapshot(
        int $start = 1_700_000_000,
        ?int $locationId = 7,
        string $locationName = 'Main Hall',
        bool $canceled = false,
    ): array {
        return [
            'start' => $start,
            'startFormatted' => (new DateTimeImmutable())->setTimestamp($start)->format('Y-m-d H:i'),
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
        $event->method('getStart')->willReturn(new \DateTime('2026-06-01 19:00:00'));
        $event->method('getLocation')->willReturn($location);
        $event->method('getTitle')->willReturn('Test Event');
        $event->method('getId')->willReturn(42);

        return $event;
    }
}
