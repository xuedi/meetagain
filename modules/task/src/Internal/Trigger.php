<?php declare(strict_types=1);

namespace Module\Task\Internal;

use Module\Task\Contract\TriggerInterface;
use Module\Task\Internal\Message\RunTask;
use Override;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

readonly class Trigger implements TriggerInterface
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {}

    #[Override]
    public function runSoon(string $identifier): void
    {
        $this->bus->dispatch(new RunTask($identifier), [new TransportNamesStamp([ScheduleProvider::OFFLOAD_TRANSPORT])]);
    }
}
