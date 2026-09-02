<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Entity\Event;
use App\Filter\Event\EventFilterResult;
use App\Filter\Event\EventFilterService;
use App\Repository\EventRepository;
use App\Twig\CmsEventTeaserRuntime;
use PHPUnit\Framework\TestCase;

class CmsEventTeaserRuntimeTest extends TestCase
{
    public function testReturnsEmptyArrayWhenFilterYieldsNoEvents(): void
    {
        // Arrange
        $filterServiceStub = $this->createStub(EventFilterService::class);
        $filterServiceStub->method('getEventIdFilter')->willReturn(EventFilterResult::emptyResult());

        $repoMock = $this->createMock(EventRepository::class);
        $repoMock->expects($this->once())->method('getUpcomingEvents')->with(3, [])->willReturn([]);

        $subject = new CmsEventTeaserRuntime($filterServiceStub, $repoMock);

        // Act
        $result = $subject->getUpcomingEvents();

        // Assert
        static::assertSame([], $result);
    }

    public function testReturnsRepositoryResultWhenFilterIsNull(): void
    {
        // Arrange
        $events = [$this->createStub(Event::class), $this->createStub(Event::class), $this->createStub(Event::class)];

        $filterServiceStub = $this->createStub(EventFilterService::class);
        $filterServiceStub->method('getEventIdFilter')->willReturn(EventFilterResult::noFilter());

        $repoMock = $this->createMock(EventRepository::class);
        $repoMock->expects($this->once())->method('getUpcomingEvents')->with(3, null)->willReturn($events);

        $subject = new CmsEventTeaserRuntime($filterServiceStub, $repoMock);

        // Act
        $result = $subject->getUpcomingEvents();

        // Assert
        static::assertSame($events, $result);
    }

    public function testForwardsRestrictedEventIdsToRepository(): void
    {
        // Arrange
        $events = [$this->createStub(Event::class)];

        $filterServiceStub = $this->createStub(EventFilterService::class);
        $filterServiceStub->method('getEventIdFilter')->willReturn(new EventFilterResult([1, 2, 5, 8], true));

        $repoMock = $this->createMock(EventRepository::class);
        $repoMock->expects($this->once())->method('getUpcomingEvents')->with(3, [1, 2, 5, 8])->willReturn($events);

        $subject = new CmsEventTeaserRuntime($filterServiceStub, $repoMock);

        // Act
        $result = $subject->getUpcomingEvents();

        // Assert
        static::assertSame($events, $result);
    }

    public function testRespectsCustomLimitArgument(): void
    {
        // Arrange
        $filterServiceStub = $this->createStub(EventFilterService::class);
        $filterServiceStub->method('getEventIdFilter')->willReturn(EventFilterResult::noFilter());

        $repoMock = $this->createMock(EventRepository::class);
        $repoMock->expects($this->once())->method('getUpcomingEvents')->with(5, null)->willReturn([]);

        $subject = new CmsEventTeaserRuntime($filterServiceStub, $repoMock);

        // Act
        $subject->getUpcomingEvents(5);

        // Assert
        static::assertTrue(true);
    }
}
