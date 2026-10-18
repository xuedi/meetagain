<?php declare(strict_types=1);

namespace Module\Task\Tests\Functional;

use Module\Task\Contract\Cadence;
use Module\Task\Internal\Message\RunTask;
use Module\Task\Internal\Registry;
use Module\Task\Internal\ScheduleProvider;
use Module\Task\Tests\Stub\Task;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Generator\MessageGenerator;
use Symfony\Contracts\Cache\CacheInterface;

final class ScheduleTest extends KernelTestCase
{
    private const string NAME = 'schedule-test';

    private CacheInterface $state;

    protected function setUp(): void
    {
        self::bootKernel();
        $state = self::getContainer()->get(ScheduleProvider::class)->getSchedule()->getState();
        self::assertInstanceOf(CacheInterface::class, $state);
        $this->state = $state;
        $this->state->delete('scheduler_checkpoint_' . self::NAME);
    }

    public function testClearingTheAppCacheOnADeployKeepsTheNextRun(): void
    {
        // Arrange
        $clock = new MockClock('2026-10-18 20:53:30');
        $provider = new ScheduleProvider(
            new Registry([new Task('every-minute', new Cadence('* * * * *'))]),
            new LockFactory(new InMemoryStore()),
            $this->state,
        );
        $generator = new MessageGenerator($provider, self::NAME, $clock);
        iterator_to_array($generator->getMessages(), false);
        $cleared = self::getContainer()->get('cache.app');
        self::assertInstanceOf(CacheInterface::class, $cleared);
        $cleared->clear();
        $clock->modify('2026-10-18 20:54:00.4');

        // Act
        $messages = iterator_to_array($generator->getMessages(), false);

        // Assert
        $identifiers = array_map(static fn(RunTask $message): string => $message->identifier, $messages);
        self::assertSame(['every-minute'], $identifiers);
    }
}
