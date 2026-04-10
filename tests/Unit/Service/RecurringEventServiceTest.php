<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\EventTranslation;
use App\EntityActionDispatcher;
use App\Enum\EventInterval;
use App\Enum\EventStatus;
use App\Repository\EventRepository;
use App\Service\Cms\CmsPageCacheService;
use App\Service\Event\RecurringEventService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Stubs\EventStub;

class RecurringEventServiceTest extends TestCase
{
    // ---- helpers ----

    private function makeEvent(int $id, EventStatus $status = EventStatus::Published): EventStub
    {
        $event = new EventStub();
        $event->setId($id);
        $event->setStart(new DateTime('2025-06-01 19:00'));
        $event->setStatus($status);

        return $event;
    }

    private function createService(EventRepository $repo, EntityManagerInterface $em): RecurringEventService
    {
        return new RecurringEventService(
            repo: $repo,
            em: $em,
            entityActionDispatcher: $this->createStub(EntityActionDispatcher::class),
            cmsPageCacheService: $this->createStub(CmsPageCacheService::class),
        );
    }

    // ---- updateRecurringEvents: early-return cases (data provider) ----

    #[DataProvider('updateRecurringEventsReturnsZeroProvider')]
    public function testUpdateRecurringEventsReturnsZero(EventStub $event, ?EventStub $parentFromRepo): void
    {
        // Arrange
        $repo = $this->createStub(EventRepository::class);
        $repo->method('findOneBy')->willReturn($parentFromRepo);
        $repo->method('findFollowUpEvents')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->createService($repo, $em);

        // Act
        $result = $service->updateRecurringEvents($event);

        // Assert
        static::assertSame(0, $result);
    }

    public static function updateRecurringEventsReturnsZeroProvider(): iterable
    {
        $noParentNoRule = new EventStub();
        $noParentNoRule->setId(1);
        $noParentNoRule->setStart(new DateTime('2025-06-01 19:00'));
        yield 'no recurring rule and no recurringOf → returns 0' => [
            'event' => $noParentNoRule,
            'parentFromRepo' => null,
        ];

        $parentZeroChildren = new EventStub();
        $parentZeroChildren->setId(2);
        $parentZeroChildren->setStart(new DateTime('2025-06-01 19:00'));
        $parentZeroChildren->setRecurringRule(EventInterval::Weekly);
        yield 'parent with zero children → returns 0' => [
            'event' => $parentZeroChildren,
            'parentFromRepo' => null,
        ];

        $child = new EventStub();
        $child->setId(10);
        $child->setStart(new DateTime('2025-06-01 19:00'));
        $child->setRecurringOf(99);
        yield 'child with parent not found in DB → returns 0' => [
            'event' => $child,
            'parentFromRepo' => null,
        ];
    }

    // ---- updateRecurringEvents: parent with unlocked children → counts and persists ----

    public function testUpdateRecurringEventsParentWithTwoUnlockedChildrenReturnsTwo(): void
    {
        // Arrange
        $parent = $this->makeEvent(1);
        $parent->setRecurringRule(EventInterval::Weekly);

        $child1 = $this->makeEvent(2);
        $child2 = $this->makeEvent(3);

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$child1, $child2]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('persist');
        $em->expects($this->once())->method('flush');

        $service = $this->createService($repo, $em);

        // Act
        $result = $service->updateRecurringEvents($parent);

        // Assert
        static::assertSame(2, $result);
    }

    public function testUpdateRecurringEventsLockedChildIsSkipped(): void
    {
        // Arrange
        $parent = $this->makeEvent(1);
        $parent->setRecurringRule(EventInterval::Monthly);

        $lockedChild = $this->makeEvent(2, EventStatus::Locked);

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$lockedChild]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->once())->method('flush');

        $service = $this->createService($repo, $em);

        // Act
        $result = $service->updateRecurringEvents($parent);

        // Assert
        static::assertSame(0, $result);
    }

    public function testUpdateRecurringEventsMixedChildrenSkipsLockedReturnsTwoUpdated(): void
    {
        // Arrange
        $parent = $this->makeEvent(1);
        $parent->setRecurringRule(EventInterval::Weekly);

        $lockedChild = $this->makeEvent(2, EventStatus::Locked);
        $unlocked1 = $this->makeEvent(3);
        $unlocked2 = $this->makeEvent(4);

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$lockedChild, $unlocked1, $unlocked2]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('persist');
        $em->expects($this->once())->method('flush');

        $service = $this->createService($repo, $em);

        // Act
        $result = $service->updateRecurringEvents($parent);

        // Assert
        static::assertSame(2, $result);
    }

    // ---- updateRecurringEvents: event is a child, parent found ----

    public function testUpdateRecurringEventsChildEventUsesParentForFollowUpLookup(): void
    {
        // Arrange
        $parentEvent = $this->makeEvent(1);
        $parentEvent->setRecurringRule(EventInterval::Monthly);

        $childEvent = $this->makeEvent(10);
        $childEvent->setRecurringOf(1);

        $followUp1 = $this->makeEvent(11);
        $followUp2 = $this->makeEvent(12);

        $repo = $this->createMock(EventRepository::class);
        $repo->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => 1])
            ->willReturn($parentEvent);
        $repo->expects($this->once())
            ->method('findFollowUpEvents')
            ->with(1, $childEvent->getStart())
            ->willReturn([$followUp1, $followUp2]);

        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->createService($repo, $em);

        // Act
        $result = $service->updateRecurringEvents($childEvent);

        // Assert
        static::assertSame(2, $result);
    }

    // ---- updateRecurringEvents: translation propagation ----

    public function testUpdateRecurringEventsPropagatesTranslationsToChildren(): void
    {
        // Arrange: parent event with one translation
        $parent = $this->makeEvent(1);
        $parent->setRecurringRule(EventInterval::Weekly);

        $translation = new EventTranslation();
        $translation->setLanguage('en');
        $translation->setTitle('Parent Title');
        $translation->setTeaser('Parent Teaser');
        $translation->setDescription('Parent Description');
        $parent->addTranslation($translation);

        // Arrange: child with no existing translation for 'en'
        $child = $this->makeEvent(2);

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$child]);

        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->createService($repo, $em);

        // Act
        $service->updateRecurringEvents($parent);

        // Assert: child now has the 'en' translation with propagated fields
        $childTranslation = $child->findTranslation('en');
        static::assertNotNull($childTranslation);
        static::assertSame('Parent Title', $childTranslation->getTitle());
        static::assertSame('Parent Teaser', $childTranslation->getTeaser());
        static::assertSame('Parent Description', $childTranslation->getDescription());
    }

    public function testUpdateRecurringEventsUpdatesExistingChildTranslation(): void
    {
        // Arrange: parent event with 'de' translation
        $parent = $this->makeEvent(1);
        $parent->setRecurringRule(EventInterval::Monthly);

        $parentTranslation = new EventTranslation();
        $parentTranslation->setLanguage('de');
        $parentTranslation->setTitle('Neuer Titel');
        $parentTranslation->setTeaser('Neuer Teaser');
        $parentTranslation->setDescription('Neue Beschreibung');
        $parent->addTranslation($parentTranslation);

        // Arrange: child already has a 'de' translation with old data
        $child = $this->makeEvent(2);
        $existingTranslation = new EventTranslation();
        $existingTranslation->setLanguage('de');
        $existingTranslation->setTitle('Alter Titel');
        $existingTranslation->setTeaser('Alter Teaser');
        $existingTranslation->setDescription('Alte Beschreibung');
        $child->addTranslation($existingTranslation);

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$child]);

        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->createService($repo, $em);

        // Act
        $service->updateRecurringEvents($parent);

        // Assert: existing child translation is updated in-place
        $childTranslation = $child->findTranslation('de');
        static::assertNotNull($childTranslation);
        static::assertSame('Neuer Titel', $childTranslation->getTitle());
        static::assertSame('Neuer Teaser', $childTranslation->getTeaser());
        static::assertSame('Neue Beschreibung', $childTranslation->getDescription());
    }
}
