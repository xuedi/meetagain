<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Comment\CommentService;
use App\Comment\TargetRegistry;
use App\Emails\Types\NotificationEventCanceledEmail;
use App\Entity\EventItemAssociation;
use App\EntityActionDispatcher;
use App\EntityActionInterface;
use App\Enum\EntityAction;
use App\Enum\EventInterval;
use App\Enum\EventRsvpFilter;
use App\Enum\EventSortFilter;
use App\Enum\EventTimeFilter;
use App\Enum\EventType;
use App\Item\AssociationService;
use App\Item\AttachControl;
use App\Item\AttachControlType;
use App\Item\TypeProviderInterface;
use App\Item\TypeRegistry;
use App\Repository\CommentRepository;
use App\Repository\EventRepository;
use App\Repository\EventTranslationRepository;
use App\Service\Config\PluginService;
use App\Service\Event\EventService;
use App\Service\Event\ImageBoxProviderInterface;
use App\Service\Event\RecurringService;
use App\Service\Security\ContentSanitizer;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Module\Ballot\Contract\BallotInterface;
use Module\Ballot\Contract\BallotSubject;
use Module\Proposal\Contract\Change\ChangeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Tests\Unit\Stubs\EventSeriesStub;
use Tests\Unit\Stubs\EventStub;
use Tests\Unit\Stubs\UserStub;

class EventServiceTest extends TestCase
{
    public function testStructureListGroupsEventsByYearAndMonth(): void
    {
        // Arrange
        $event1 = new EventStub()
            ->setId(1)
            ->setStart(new DateTimeImmutable('2002-01-01'));
        $event2 = new EventStub()
            ->setId(2)
            ->setStart(new DateTimeImmutable('2002-01-05'));
        $event3 = new EventStub()
            ->setId(3)
            ->setStart(new DateTimeImmutable('2002-01-07'));
        $event4 = new EventStub()
            ->setId(4)
            ->setStart(new DateTimeImmutable('2002-04-02'));
        $event5 = new EventStub()
            ->setId(5)
            ->setStart(new DateTimeImmutable('2002-04-04'));

        $eventList = [$event4, $event1, $event3, $event5, $event2];

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            notificationEventCanceledEmail: $this->createStub(NotificationEventCanceledEmail::class),
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $this->createStub(RecurringService::class),
            itemAssociationService: $this->createStub(AssociationService::class),
            itemTypeRegistry: $this->createStub(TypeRegistry::class),
            plugins: [],
            imageBoxProviders: [],
            translationRepo: $this->createStub(EventTranslationRepository::class),
            commentService: $this->commentService(),
            changeProposals: $this->createStub(ChangeInterface::class),
            ballots: $this->createStub(BallotInterface::class),
            entityActionDispatcher: new EntityActionDispatcher([]),
        );

        // Act
        $method = new ReflectionClass($subject)->getMethod('structureList');

        $result = $method->invoke($subject, $eventList);

        // Assert
        static::assertArrayHasKey('2002-01', $result);
        static::assertArrayHasKey('2002-04', $result);

        static::assertSame('2002', $result['2002-01']['year']);
        static::assertSame('January', $result['2002-01']['month']);
        static::assertCount(3, $result['2002-01']['events']);

        static::assertSame('2002', $result['2002-04']['year']);
        static::assertSame('April', $result['2002-04']['month']);
        static::assertCount(2, $result['2002-04']['events']);
    }

    #[DataProvider('filteredListDataProvider')]
    public function testGetFilteredListAppliesCorrectCriteria(EventTimeFilter $time, EventSortFilter $sort, EventType $types, EventRsvpFilter $rsvp): void
    {
        // Arrange
        $repoMock = $this->createMock(EventRepository::class);
        $repoMock->expects($this->once())->method('findByFilters')->with($time, $sort, $types, null, $rsvp)->willReturn([]);

        $subject = new EventService(
            repo: $repoMock,
            em: $this->createStub(EntityManagerInterface::class),
            notificationEventCanceledEmail: $this->createStub(NotificationEventCanceledEmail::class),
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $this->createStub(RecurringService::class),
            itemAssociationService: $this->createStub(AssociationService::class),
            itemTypeRegistry: $this->createStub(TypeRegistry::class),
            plugins: [],
            imageBoxProviders: [],
            translationRepo: $this->createStub(EventTranslationRepository::class),
            commentService: $this->commentService(),
            changeProposals: $this->createStub(ChangeInterface::class),
            ballots: $this->createStub(BallotInterface::class),
            entityActionDispatcher: new EntityActionDispatcher([]),
        );

        // Act
        $result = $subject->getFilteredList($time, $sort, $types, $rsvp);

        // Assert
        static::assertSame([], $result);
    }

    public static function filteredListDataProvider(): Generator
    {
        yield 'all events sorted newest first' => [
            'time' => EventTimeFilter::All,
            'sort' => EventSortFilter::NewToOld,
            'types' => EventType::All,
            'rsvp' => EventRsvpFilter::All,
        ];
    }

    public function testCancelEventSetsCanceledFlagAndSendsNotifications(): void
    {
        // Arrange
        $user1 = new UserStub()->setId(1);
        $user2 = new UserStub()->setId(2);

        $event = new EventStub()
            ->setId(1)
            ->setStart(new DateTimeImmutable('+1 week'));
        $event->addRsvp($user1);
        $event->addRsvp($user2);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($event);
        $emMock->expects($this->once())->method('flush');

        $emailMock = $this->createMock(NotificationEventCanceledEmail::class);
        $emailMock->expects($this->exactly(2))->method('send');

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $emMock,
            notificationEventCanceledEmail: $emailMock,
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $this->createStub(RecurringService::class),
            itemAssociationService: $this->createStub(AssociationService::class),
            itemTypeRegistry: $this->createStub(TypeRegistry::class),
            plugins: [],
            imageBoxProviders: [],
            translationRepo: $this->createStub(EventTranslationRepository::class),
            commentService: $this->commentService(),
            changeProposals: $this->createStub(ChangeInterface::class),
            ballots: $this->createStub(BallotInterface::class),
            entityActionDispatcher: new EntityActionDispatcher([]),
        );

        // Act
        $subject->cancelEvent($event);

        // Assert
        static::assertTrue($event->isCanceled());
    }

    public function testCancelingTwiceMailsOneRound(): void
    {
        // Arrange
        $user = new UserStub()->setId(1);
        $event = new EventStub()
            ->setId(1)
            ->setStart(new DateTimeImmutable('+1 week'));
        $event->addRsvp($user);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('flush');

        $emailMock = $this->createMock(NotificationEventCanceledEmail::class);
        $emailMock->expects($this->once())->method('send');

        $subject = $this->subjectWith($emMock, $emailMock);

        // Act
        $subject->cancelEvent($event);
        $subject->cancelEvent($event);

        // Assert
        static::assertTrue($event->isCanceled());
    }

    public function testRecancelingInsideTheNoticeWindowDoesNotMailAgain(): void
    {
        // Arrange
        $event = new EventStub()
            ->setId(1)
            ->setStart(new DateTimeImmutable('+1 week'));
        $event->addRsvp(new UserStub()->setId(1));

        $emailMock = $this->createMock(NotificationEventCanceledEmail::class);
        $emailMock->expects($this->once())->method('send');

        $subject = $this->subjectWith($this->createStub(EntityManagerInterface::class), $emailMock);

        // Act
        $subject->cancelEvent($event);
        $subject->uncancelEvent($event);
        $subject->cancelEvent($event);

        // Assert
        static::assertTrue($event->isCanceled());
    }

    public function testCancelEventWithNoRsvpsDoesNotSendEmails(): void
    {
        // Arrange
        $event = new EventStub()
            ->setId(1)
            ->setStart(new DateTimeImmutable('+1 week'));

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($event);
        $emMock->expects($this->once())->method('flush');

        $emailMock = $this->createMock(NotificationEventCanceledEmail::class);
        $emailMock->expects($this->never())->method('send');

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $emMock,
            notificationEventCanceledEmail: $emailMock,
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $this->createStub(RecurringService::class),
            itemAssociationService: $this->createStub(AssociationService::class),
            itemTypeRegistry: $this->createStub(TypeRegistry::class),
            plugins: [],
            imageBoxProviders: [],
            translationRepo: $this->createStub(EventTranslationRepository::class),
            commentService: $this->commentService(),
            changeProposals: $this->createStub(ChangeInterface::class),
            ballots: $this->createStub(BallotInterface::class),
            entityActionDispatcher: new EntityActionDispatcher([]),
        );

        // Act
        $subject->cancelEvent($event);

        // Assert
        static::assertTrue($event->isCanceled());
    }

    public function testCancelPastEventDoesNotSendNotifications(): void
    {
        // Arrange
        $user = new UserStub()->setId(1);
        $event = new EventStub()
            ->setId(1)
            ->setStart(new DateTimeImmutable('-1 week'));
        $event->addRsvp($user);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($event);
        $emMock->expects($this->once())->method('flush');

        $emailMock = $this->createMock(NotificationEventCanceledEmail::class);
        $emailMock->expects($this->never())->method('send');

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $emMock,
            notificationEventCanceledEmail: $emailMock,
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $this->createStub(RecurringService::class),
            itemAssociationService: $this->createStub(AssociationService::class),
            itemTypeRegistry: $this->createStub(TypeRegistry::class),
            plugins: [],
            imageBoxProviders: [],
            translationRepo: $this->createStub(EventTranslationRepository::class),
            commentService: $this->commentService(),
            changeProposals: $this->createStub(ChangeInterface::class),
            ballots: $this->createStub(BallotInterface::class),
            entityActionDispatcher: new EntityActionDispatcher([]),
        );

        // Act
        $subject->cancelEvent($event);

        // Assert
        static::assertTrue($event->isCanceled());
    }

    public function testUncancelEventRemovesCanceledFlag(): void
    {
        // Arrange
        $event = new EventStub()->setId(1);
        $event->setCanceled(true);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($event);
        $emMock->expects($this->once())->method('flush');

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $emMock,
            notificationEventCanceledEmail: $this->createStub(NotificationEventCanceledEmail::class),
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $this->createStub(RecurringService::class),
            itemAssociationService: $this->createStub(AssociationService::class),
            itemTypeRegistry: $this->createStub(TypeRegistry::class),
            plugins: [],
            imageBoxProviders: [],
            translationRepo: $this->createStub(EventTranslationRepository::class),
            commentService: $this->commentService(),
            changeProposals: $this->createStub(ChangeInterface::class),
            ballots: $this->createStub(BallotInterface::class),
            entityActionDispatcher: new EntityActionDispatcher([]),
        );

        // Act
        $subject->uncancelEvent($event);

        // Assert
        static::assertFalse($event->isCanceled());
    }

    public function testUpdateRecurringEventsWithNoRecurringReturnZero(): void
    {
        // Arrange
        $event = new EventStub()->setId(1);

        $recurringServiceMock = $this->createMock(RecurringService::class);
        $recurringServiceMock->expects($this->once())->method('updateRecurringEvents')->with($event)->willReturn(0);

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            notificationEventCanceledEmail: $this->createStub(NotificationEventCanceledEmail::class),
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $recurringServiceMock,
            itemAssociationService: $this->createStub(AssociationService::class),
            itemTypeRegistry: $this->createStub(TypeRegistry::class),
            plugins: [],
            imageBoxProviders: [],
            translationRepo: $this->createStub(EventTranslationRepository::class),
            commentService: $this->commentService(),
            changeProposals: $this->createStub(ChangeInterface::class),
            ballots: $this->createStub(BallotInterface::class),
            entityActionDispatcher: new EntityActionDispatcher([]),
        );

        // Act & Assert
        static::assertSame(0, $subject->updateRecurringEvents($event));
    }

    public function testUpdateRecurringEventsWithSeriesMemberReturnsCount(): void
    {
        // Arrange
        $parent = new EventStub()
            ->setId(1)
            ->setSeries(
                new EventSeriesStub()
                    ->setId(9)
                    ->setRule(EventInterval::Weekly),
            );
        $parent->setStart(new DateTimeImmutable('2025-01-01 10:00:00'));

        $recurringServiceMock = $this->createMock(RecurringService::class);
        $recurringServiceMock->expects($this->once())->method('updateRecurringEvents')->with($parent)->willReturn(1);

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            notificationEventCanceledEmail: $this->createStub(NotificationEventCanceledEmail::class),
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $recurringServiceMock,
            itemAssociationService: $this->createStub(AssociationService::class),
            itemTypeRegistry: $this->createStub(TypeRegistry::class),
            plugins: [],
            imageBoxProviders: [],
            translationRepo: $this->createStub(EventTranslationRepository::class),
            commentService: $this->commentService(),
            changeProposals: $this->createStub(ChangeInterface::class),
            ballots: $this->createStub(BallotInterface::class),
            entityActionDispatcher: new EntityActionDispatcher([]),
        );

        // Act & Assert
        static::assertSame(1, $subject->updateRecurringEvents($parent));
    }

    public function testCancelEventSendsEmailsAndFlushes(): void
    {
        // Arrange
        $user = new UserStub()
            ->setId(1)
            ->setEmail('test@example.com');
        $event = new EventStub()
            ->setId(1)
            ->setStart(new DateTimeImmutable('+1 week'));
        $event->addRsvp($user);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($event);
        $emMock->expects($this->once())->method('flush');

        $emailMock = $this->createMock(NotificationEventCanceledEmail::class);
        $emailMock->expects($this->once())->method('send')->with(['user' => $user, 'event' => $event]);

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $emMock,
            notificationEventCanceledEmail: $emailMock,
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $this->createStub(RecurringService::class),
            itemAssociationService: $this->createStub(AssociationService::class),
            itemTypeRegistry: $this->createStub(TypeRegistry::class),
            plugins: [],
            imageBoxProviders: [],
            translationRepo: $this->createStub(EventTranslationRepository::class),
            commentService: $this->commentService(),
            changeProposals: $this->createStub(ChangeInterface::class),
            ballots: $this->createStub(BallotInterface::class),
            entityActionDispatcher: new EntityActionDispatcher([]),
        );

        // Act & Assert
        $subject->cancelEvent($event);
        static::assertTrue($event->isCanceled());
    }

    public function testUpdateRecurringEventsWithAutoMemberDelegates(): void
    {
        // Arrange
        $child = new EventStub()
            ->setId(2)
            ->setSeries(
                new EventSeriesStub()
                    ->setId(9)
                    ->setRule(EventInterval::Weekly),
            )
            ->setStart(new DateTimeImmutable('+1 week'));

        $recurringServiceMock = $this->createMock(RecurringService::class);
        $recurringServiceMock->expects($this->once())->method('updateRecurringEvents')->with($child)->willReturn(1);

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            notificationEventCanceledEmail: $this->createStub(NotificationEventCanceledEmail::class),
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $recurringServiceMock,
            itemAssociationService: $this->createStub(AssociationService::class),
            itemTypeRegistry: $this->createStub(TypeRegistry::class),
            plugins: [],
            imageBoxProviders: [],
            translationRepo: $this->createStub(EventTranslationRepository::class),
            commentService: $this->commentService(),
            changeProposals: $this->createStub(ChangeInterface::class),
            ballots: $this->createStub(BallotInterface::class),
            entityActionDispatcher: new EntityActionDispatcher([]),
        );

        // Act & Assert
        static::assertSame(1, $subject->updateRecurringEvents($child));
    }

    public function testUpdateRecurringEventsWithClosedSeriesReturnsZero(): void
    {
        // Arrange
        $child = new EventStub()
            ->setId(2)
            ->setSeries(
                new EventSeriesStub()
                    ->setId(9)
                    ->setRule(null),
            )
            ->setStart(new DateTimeImmutable('+1 week'));

        $recurringServiceMock = $this->createMock(RecurringService::class);
        $recurringServiceMock->expects($this->once())->method('updateRecurringEvents')->with($child)->willReturn(0);

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            notificationEventCanceledEmail: $this->createStub(NotificationEventCanceledEmail::class),
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $recurringServiceMock,
            itemAssociationService: $this->createStub(AssociationService::class),
            itemTypeRegistry: $this->createStub(TypeRegistry::class),
            plugins: [],
            imageBoxProviders: [],
            translationRepo: $this->createStub(EventTranslationRepository::class),
            commentService: $this->commentService(),
            changeProposals: $this->createStub(ChangeInterface::class),
            ballots: $this->createStub(BallotInterface::class),
            entityActionDispatcher: new EntityActionDispatcher([]),
        );

        // Act & Assert
        static::assertSame(0, $subject->updateRecurringEvents($child));
    }

    public function testTheFirstActivePluginClaimingTheImageBoxWins(): void
    {
        // Arrange
        $subject = $this->serviceWithImageBoxProviders(
            ['photos', 'films'],
            $this->imageBoxProvider('films', '<div>films</div>'),
            $this->imageBoxProvider('photos', '<div>photos</div>'),
        );

        // Act & Assert
        static::assertSame('<div>films</div>', $subject->getPluginImageBox(4));
    }

    public function testAnInactivePluginNeverClaimsTheImageBox(): void
    {
        // Arrange
        $subject = $this->serviceWithImageBoxProviders(
            ['films'],
            $this->imageBoxProvider('photos', '<div>photos</div>'),
            $this->imageBoxProvider('films', null),
        );

        // Act & Assert
        static::assertNull($subject->getPluginImageBox(4));
    }

    private function imageBoxProvider(string $pluginKey, ?string $markup): ImageBoxProviderInterface
    {
        $provider = $this->createStub(ImageBoxProviderInterface::class);
        $provider->method('getPluginKey')->willReturn($pluginKey);
        $provider->method('renderImageBox')->willReturn($markup);

        return $provider;
    }

    /** @param list<string> $activePlugins */
    private function serviceWithImageBoxProviders(array $activePlugins, ImageBoxProviderInterface ...$providers): EventService
    {
        $pluginService = $this->createStub(PluginService::class);
        $pluginService->method('getActiveList')->willReturn($activePlugins);

        return new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            notificationEventCanceledEmail: $this->createStub(NotificationEventCanceledEmail::class),
            pluginService: $pluginService,
            recurringEventService: $this->createStub(RecurringService::class),
            itemAssociationService: $this->createStub(AssociationService::class),
            itemTypeRegistry: $this->createStub(TypeRegistry::class),
            plugins: [],
            imageBoxProviders: $providers,
            translationRepo: $this->createStub(EventTranslationRepository::class),
            commentService: $this->commentService(),
            changeProposals: $this->createStub(ChangeInterface::class),
            ballots: $this->createStub(BallotInterface::class),
            entityActionDispatcher: new EntityActionDispatcher([]),
        );
    }

    public function testItemHeadingKeyUsesTheProviderOfASingleAttachedType(): void
    {
        // Arrange
        $subject = $this->buildHeadingSubject(['books', 'books'], ['books' => 'books.event_heading']);

        // Act
        $result = $subject->getItemHeadingKey(1, null);

        // Assert
        $this->assertSame('books.event_heading', $result);
    }

    public function testItemHeadingKeyFallsBackToGenericForMixedTypes(): void
    {
        // Arrange
        $subject = $this->buildHeadingSubject(['books', 'films'], ['books' => 'books.event_heading', 'films' => 'films.event_heading']);

        // Act
        $result = $subject->getItemHeadingKey(1, null);

        // Assert
        $this->assertSame('item.heading', $result);
    }

    public function testItemHeadingKeyUsesTheOnlyAttachableTypeWithoutAttachments(): void
    {
        // Arrange
        $subject = $this->buildHeadingSubject([], ['films' => 'films.event_heading']);
        $control = new AttachControl(1, [$this->attachType('films')]);

        // Act
        $result = $subject->getItemHeadingKey(1, $control);

        // Assert
        $this->assertSame('films.event_heading', $result);
    }

    public function testItemHeadingKeyIsGenericWithoutAttachmentsAndSeveralAttachableTypes(): void
    {
        // Arrange
        $subject = $this->buildHeadingSubject([], ['films' => 'films.event_heading', 'books' => 'books.event_heading']);
        $control = new AttachControl(1, [$this->attachType('films'), $this->attachType('books')]);

        // Act
        $result = $subject->getItemHeadingKey(1, $control);

        // Assert
        $this->assertSame('item.heading', $result);
    }

    public function testItemHeadingKeyResolvesAnAttachedTypeOfAnInactivePlugin(): void
    {
        // Arrange
        $subject = $this->buildHeadingSubject(['dishes'], ['dishes' => 'dishes.event_heading']);

        // Act
        $result = $subject->getItemHeadingKey(1, new AttachControl(1, []));

        // Assert
        $this->assertSame('dishes.event_heading', $result);
    }

    private function attachType(string $key): AttachControlType
    {
        return new AttachControlType($key, $key . '.item_label', $key . '.attach_heading', $key . '.attach_help', '', []);
    }

    /**
     * @param list<string> $attachedTypes
     * @param array<string, string> $headingKeysByType
     */
    private function buildHeadingSubject(array $attachedTypes, array $headingKeysByType): EventService
    {
        $associations = [];
        foreach ($attachedTypes as $type) {
            $associations[] = new EventItemAssociation()->setItemType($type);
        }

        $associationService = $this->createStub(AssociationService::class);
        $associationService->method('listForEvent')->willReturn($associations);

        $registry = $this->createStub(TypeRegistry::class);
        $registry
            ->method('providerForIncludingInactive')
            ->willReturnCallback(function (string $type) use ($headingKeysByType): ?TypeProviderInterface {
                if (!isset($headingKeysByType[$type])) {
                    return null;
                }
                $provider = $this->createStub(TypeProviderInterface::class);
                $provider->method('getEventHeadingKey')->willReturn($headingKeysByType[$type]);

                return $provider;
            });

        return new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            notificationEventCanceledEmail: $this->createStub(NotificationEventCanceledEmail::class),
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $this->createStub(RecurringService::class),
            itemAssociationService: $associationService,
            itemTypeRegistry: $registry,
            plugins: [],
            imageBoxProviders: [],
            translationRepo: $this->createStub(EventTranslationRepository::class),
            commentService: $this->commentService(),
            changeProposals: $this->createStub(ChangeInterface::class),
            ballots: $this->createStub(BallotInterface::class),
            entityActionDispatcher: new EntityActionDispatcher([]),
        );
    }

    private function subjectWith(EntityManagerInterface $em, NotificationEventCanceledEmail $email): EventService
    {
        return new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $em,
            notificationEventCanceledEmail: $email,
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $this->createStub(RecurringService::class),
            itemAssociationService: $this->createStub(AssociationService::class),
            itemTypeRegistry: $this->createStub(TypeRegistry::class),
            plugins: [],
            imageBoxProviders: [],
            translationRepo: $this->createStub(EventTranslationRepository::class),
            commentService: $this->commentService(),
            changeProposals: $this->createStub(ChangeInterface::class),
            ballots: $this->createStub(BallotInterface::class),
            entityActionDispatcher: new EntityActionDispatcher([]),
        );
    }

    public function testDeleteClearsTheProposalsAndBallotsOfTheEventAndAnnouncesIt(): void
    {
        // Arrange
        $event = new EventStub()
            ->setId(5)
            ->setStart(new DateTimeImmutable('2030-01-01'));
        $changeProposals = $this->createMock(ChangeInterface::class);
        $changeProposals->expects($this->once())->method('removeForTarget')->with('event', 5);
        $ballots = $this->createMock(BallotInterface::class);
        $ballots->expects($this->once())->method('purgeForSubject')->with(new BallotSubject('event', 5));
        $handler = new class implements EntityActionInterface {
            /** @var list<array{EntityAction, int}> */
            public array $announced = [];

            public function onEntityAction(EntityAction $action, int $entityId): void
            {
                $this->announced[] = [$action, $entityId];
            }
        };
        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            notificationEventCanceledEmail: $this->createStub(NotificationEventCanceledEmail::class),
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $this->createStub(RecurringService::class),
            itemAssociationService: $this->createStub(AssociationService::class),
            itemTypeRegistry: $this->createStub(TypeRegistry::class),
            plugins: [],
            imageBoxProviders: [],
            translationRepo: $this->createStub(EventTranslationRepository::class),
            commentService: $this->commentService(),
            changeProposals: $changeProposals,
            ballots: $ballots,
            entityActionDispatcher: new EntityActionDispatcher([$handler]),
        );

        // Act
        $subject->delete($event);

        // Assert
        static::assertSame([[EntityAction::DeleteEvent, 5]], $handler->announced);
    }

    private function commentService(): CommentService
    {
        $config = new HtmlSanitizerConfig()->allowSafeElements();

        return new CommentService(
            $this->createStub(CommentRepository::class),
            $this->createStub(EntityManagerInterface::class),
            new ContentSanitizer(new HtmlSanitizer($config), new HtmlSanitizer($config)),
            new TargetRegistry([]),
        );
    }
}
