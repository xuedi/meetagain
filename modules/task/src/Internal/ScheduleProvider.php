<?php declare(strict_types=1);

namespace Module\Task\Internal;

use Module\Task\Internal\Message\RunTask;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
readonly class ScheduleProvider implements ScheduleProviderInterface
{
    public const string OFFLOAD_TRANSPORT = 'async';
    private const string LOCK_NAME = 'task-schedule';

    public function __construct(
        private Registry $registry,
        private LockFactory $lockFactory,
        #[Autowire(service: 'cache.task_schedule')]
        private CacheInterface $cache,
    ) {}

    public function getSchedule(): Schedule
    {
        $schedule = new Schedule()
            ->lock($this->lockFactory->createLock(self::LOCK_NAME))
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);

        $timezone = date_default_timezone_get();
        foreach ($this->registry->all() as $identifier => $task) {
            $cadence = $task->getCadence();
            $message = new RunTask($identifier);
            $schedule->add(RecurringMessage::trigger(
                new CadenceTrigger($identifier, $cadence, $timezone),
                $cadence->offloaded ? new RedispatchMessage($message, self::OFFLOAD_TRANSPORT) : $message,
            ));
        }

        return $schedule;
    }
}
