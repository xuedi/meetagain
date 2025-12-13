<?php
declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\EventFilterRsvp;
use App\Entity\EventFilterSort;
use App\Entity\EventFilterTime;
use App\Entity\EventTypes;
use App\Repository\EventRepository;
use App\Service\EventService;
use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\LazyCriteriaCollection;
use Generator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Unit\Stubs\EventStub;

#[AllowMockObjectsWithoutExpectations]
class EventServiceTest extends TestCase
{
    private MockObject|EventRepository $eventRepoMock;
    private MockObject|EntityManagerInterface $emMock;
    private EventService $subject;

    protected function setUp(): void
    {
        $this->eventRepoMock = $this->createMock(EventRepository::class);
        $this->emMock = $this->createMock(EntityManagerInterface::class);
        $this->subject = new EventService(
            repo: $this->eventRepoMock,
            em: $this->emMock,
        );
    }

    public function testCanStructureEventList(): void
    {
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

        $eventListParameter = [$event4, $event1, $event3, $event5, $event2];
        $expectedResult = [
            '2002-01' => [
                'year' => '2002',
                'month' => 'January',
                'events' => [$event1, $event3, $event2],
            ],
            '2002-04' => [
                'year' => '2002',
                'month' => 'April',
                'events' => [$event4, $event5],
            ],
        ];

        $reflection = new ReflectionClass($this->subject);
        $method = $reflection->getMethod('structureList');
        $method->setAccessible(true);

        $this->assertEquals($expectedResult, $method->invoke($this->subject, $eventListParameter));
    }

    #[DataProvider('getLastRecurringEventMatrix')]
    public function testLastRecurringEvent(null|string $dbResult, string $parameter, string $expected): void
    {
        $returnValue = null;
        if ($dbResult !== null) {
            $returnValue = new EventStub()->setStart(new DateTimeImmutable($dbResult));
        }
        $this->eventRepoMock->method('findOneBy')->willReturn($returnValue);

        $parameter = new EventStub()->setStart(new DateTimeImmutable($parameter));

        $reflection = new ReflectionClass($this->subject);
        $method = $reflection->getMethod('getLastRecurringEventDate');
        $method->setAccessible(true);

        $this->assertEquals($expected, $method->invoke($this->subject, $parameter));
    }

    public static function getLastRecurringEventMatrix(): array
    {
        return [
            [
                '1' => '2017-04-26',
                '2' => '2002-01-01',
                '3' => '2017-04-26',
            ],
            [
                '1' => null,
                '2' => '2002-01-01',
                '3' => '2002-01-01',
            ],
        ];
    }

    public function testCanUpdateDatetime(): void
    {
        $paraTarget = new DateTime('2010-10-10 10:10:10');
        $paraOccurrence = new DateTime('2002-02-02 20:20:20');
        $expected = new DateTime('2002-02-02 10:10:10');

        $reflection = new ReflectionClass($this->subject);
        $method = $reflection->getMethod('updateDate');
        $method->setAccessible(true);

        $actual = $method->invoke($this->subject, $paraTarget, $paraOccurrence);

        $this->assertEquals($expected->format('Y-m-d H:i:s'), $actual->format('Y-m-d H:i:s'));
    }

    #[DataProvider('getFilteredListMatrix')]
    public function testGetFilteredList(
        EventFilterTime $time,
        EventFilterSort $sort,
        EventTypes $types,
        EventFilterRsvp $rsvp,
        Criteria $expectedCriteria,
    ): void {
        $collectionMock = $this->createMock(LazyCriteriaCollection::class);
        $collectionMock->expects($this->once())->method('toArray')->willReturn([]);

        $this->eventRepoMock
            ->expects($this->once())
            ->method('matching')
            ->with($expectedCriteria)
            ->willReturn($collectionMock);

        $this->subject->getFilteredList($time, $sort, $types, $rsvp);
    }

    public static function getFilteredListMatrix(): Generator
    {
        yield [
            EventFilterTime::All,
            EventFilterSort::NewToOld,
            EventTypes::All,
            EventFilterRsvp::All,
            new Criteria()
                ->orderBy(['start' => 'desc'])
                ->where(Criteria::expr()->not(Criteria::expr()->eq('id', 0)))
                ->andWhere(Criteria::expr()->not(Criteria::expr()->eq('id', 0)))
                ->andWhere(Criteria::expr()->eq('published', true)),
        ];
    }
}
