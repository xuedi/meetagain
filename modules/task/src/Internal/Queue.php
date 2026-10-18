<?php declare(strict_types=1);

namespace Module\Task\Internal;

use DateTimeInterface;
use Module\Task\Contract\Health;
use Module\Task\Contract\HealthInterface;
use Module\Task\Internal\Message\RunTask;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

readonly class Queue implements HealthInterface
{
    public const string SCHEDULER_TRANSPORT = 'scheduler_default';
    private const int FAILED_LIST_LIMIT = 200;

    public function __construct(
        private Heartbeat $heartbeat,
        private MessageBusInterface $bus,
        #[Autowire(service: 'messenger.transport.async')]
        private TransportInterface $async,
        #[Autowire(service: 'messenger.transport.failed')]
        private TransportInterface $failed,
    ) {}

    #[Override]
    public function health(): Health
    {
        $workers = 0;
        $schedulers = 0;
        foreach ($this->heartbeat->alive() as $process) {
            if (in_array(ScheduleProvider::OFFLOAD_TRANSPORT, $process['transports'], true)) {
                $workers++;
            }
            if (in_array(self::SCHEDULER_TRANSPORT, $process['transports'], true)) {
                $schedulers++;
            }
        }

        return new Health($workers, $schedulers, $this->failedCount());
    }

    /**
     * @return list<array{host: string, pid: int, transports: list<string>, startedAt: string, seenAt: string, handled: int, failed: int, memoryBytes: int}>
     */
    public function processes(): array
    {
        return $this->heartbeat->alive();
    }

    public function pendingCount(): ?int
    {
        return $this->async instanceof MessageCountAwareInterface ? $this->async->getMessageCount() : null;
    }

    public function failedCount(): ?int
    {
        return $this->failed instanceof MessageCountAwareInterface ? $this->failed->getMessageCount() : null;
    }

    /**
     * @return list<array{id: string, message: string, error: string, failedAt: ?DateTimeInterface, transport: string}>
     */
    public function failedMessages(): array
    {
        if (!$this->failed instanceof ListableReceiverInterface) {
            return [];
        }

        $messages = [];
        foreach ($this->failed->all(self::FAILED_LIST_LIMIT) as $envelope) {
            $error = $envelope->last(ErrorDetailsStamp::class);
            $messages[] = [
                'id' => (string) $envelope->last(TransportMessageIdStamp::class)?->getId(),
                'message' => $this->describe($envelope),
                'error' => $error === null ? '' : sprintf('%s: %s', $error->getExceptionClass(), $error->getExceptionMessage()),
                'failedAt' => $envelope->last(RedeliveryStamp::class)?->getRedeliveredAt(),
                'transport' => $this->originalTransport($envelope),
            ];
        }

        return $messages;
    }

    public function retry(string $id): bool
    {
        $envelope = $this->findFailed($id);
        if ($envelope === null) {
            return false;
        }

        $this->bus->dispatch($envelope->getMessage(), [new TransportNamesStamp([$this->originalTransport($envelope)])]);
        $this->failed->reject($envelope);

        return true;
    }

    public function delete(string $id): bool
    {
        $envelope = $this->findFailed($id);
        if ($envelope === null) {
            return false;
        }

        $this->failed->reject($envelope);

        return true;
    }

    private function findFailed(string $id): ?Envelope
    {
        return $this->failed instanceof ListableReceiverInterface ? $this->failed->find($id) : null;
    }

    private function originalTransport(Envelope $envelope): string
    {
        return $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName() ?? ScheduleProvider::OFFLOAD_TRANSPORT;
    }

    private function describe(Envelope $envelope): string
    {
        $message = $envelope->getMessage();

        return $message instanceof RunTask ? sprintf('%s %s', $message::class, $message->identifier) : $message::class;
    }
}
