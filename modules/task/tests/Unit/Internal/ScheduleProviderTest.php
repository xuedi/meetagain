<?php declare(strict_types=1);

namespace Module\Task\Tests\Unit\Internal;

use DateTimeImmutable;
use Module\Task\Contract\Cadence;
use Module\Task\Internal\Message\RunTask;
use Module\Task\Internal\Registry;
use Module\Task\Internal\ScheduleProvider;
use Module\Task\Tests\Stub\Task;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Generator\MessageGenerator;
use Symfony\Component\Scheduler\RecurringMessage;

final class ScheduleProviderTest extends TestCase
{
    public function testAnInlineTaskIsScheduledAsARunMessage(): void
    {
        // Arrange
        $provider = $this->provider(new Task('inline', new Cadence('0 3 * * *')));

        // Act
        $message = $this->onlyMessage($provider);

        // Assert
        self::assertInstanceOf(RunTask::class, $message);
        self::assertSame('inline', $message->identifier);
    }

    public function testAnOffloadedTaskIsRedispatchedToTheWorkers(): void
    {
        // Arrange
        $provider = $this->provider(new Task('heavy', new Cadence('0 3 * * *', offloaded: true)));

        // Act
        $message = $this->onlyMessage($provider);

        // Assert
        self::assertInstanceOf(RedispatchMessage::class, $message);
        self::assertSame(ScheduleProvider::OFFLOAD_TRANSPORT, $message->transportNames);
        self::assertInstanceOf(RunTask::class, $message->envelope);
    }

    public function testTheScheduleIsLockedAndStateful(): void
    {
        // Arrange
        $provider = $this->provider(new Task());

        // Act
        $schedule = $provider->getSchedule();

        // Assert
        self::assertNotNull($schedule->getLock());
        self::assertNotNull($schedule->getState());
        self::assertTrue($schedule->shouldProcessOnlyLastMissedRun());
    }

    public function testASchedulerRestartBeforeTheJitteredTimeKeepsTheRun(): void
    {
        // Arrange
        $tasks = [new Task('every-minute', new Cadence('* * * * *')), new Task('cleanup', new Cadence('5 * * * *', 600))];
        $store = new InMemoryStore();
        $state = new ArrayAdapter();
        $clock = new MockClock('2026-10-18 04:06:30');
        $before = $this->generator($tasks, $store, $state, $clock);
        iterator_to_array($before->getMessages(), false);
        $clock->modify('2026-10-18 04:07:10');
        iterator_to_array($before->getMessages(), false);
        unset($before);
        $clock->modify('2026-10-18 04:15:00');

        // Act
        $messages = iterator_to_array($this->generator($tasks, $store, $state, $clock)->getMessages(), false);

        // Assert
        $identifiers = array_map(static fn(RunTask $message): string => $message->identifier, $messages);
        self::assertContains('cleanup', $identifiers);
    }

    /** @param list<Task> $tasks */
    private function generator(array $tasks, InMemoryStore $store, ArrayAdapter $state, MockClock $clock): MessageGenerator
    {
        $provider = new ScheduleProvider(new Registry($tasks), new LockFactory($store), $state);

        return new MessageGenerator($provider, 'default', $clock);
    }

    private function provider(Task $task): ScheduleProvider
    {
        return new ScheduleProvider(new Registry([$task]), new LockFactory(new InMemoryStore()), new ArrayAdapter());
    }

    private function onlyMessage(ScheduleProvider $provider): object
    {
        $recurring = $provider->getSchedule()->getRecurringMessages();
        self::assertCount(1, $recurring);
        $first = array_values($recurring)[0];
        self::assertInstanceOf(RecurringMessage::class, $first);
        $context = new MessageContext('default', $first->getId(), $first->getTrigger(), new DateTimeImmutable());
        $messages = iterator_to_array($first->getMessages($context), false);
        self::assertCount(1, $messages);

        return $messages[0];
    }
}
