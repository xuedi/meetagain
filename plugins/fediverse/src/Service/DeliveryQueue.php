<?php declare(strict_types=1);

namespace Plugin\Fediverse\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use Module\Task\Contract\TriggerInterface;
use Override;
use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Entity\Delivery;
use Plugin\Fediverse\Repository\DeliveryRepository;
use Plugin\Fediverse\Security\HttpSignature;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

readonly class DeliveryQueue implements TaskInterface
{
    public const int TICK_BUDGET = 100;
    public const int BATCH_SIZE = 20;
    public const int RUN_SECONDS = 120;
    public const int SEND_TIMEOUT_SECONDS = 5;
    public const int SEND_MAX_DURATION_SECONDS = 15;
    public const int LEASE_SECONDS = 600;
    public const array RETRY_MINUTES = [1, 5, 30, 120, 360, 720, 1440, 1440];

    private const array RETRYABLE_CLIENT_ERRORS = [408, 429];

    public function __construct(
        private DeliveryRepository $repository,
        private HttpSignature $httpSignature,
        #[Autowire(service: 'remote.client')]
        private HttpClientInterface $client,
        private EntityManagerInterface $em,
        private ConfigService $configService,
        private TriggerInterface $trigger,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param list<string>         $inboxes
     * @param array<string, mixed> $activity
     */
    public function queue(Account $account, array $inboxes, array $activity): void
    {
        $inboxes = array_filter(array_unique($inboxes), fn(string $inbox): bool => !$this->isBlocked($inbox));
        if ($inboxes === []) {
            return;
        }

        $json = json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        foreach ($inboxes as $inbox) {
            $this->em->persist(new Delivery($account, $inbox, $json, $this->clock->now()));
        }
        $this->em->flush();
        $this->trigger->runSoon($this->getIdentifier());
    }

    #[Override]
    public function getIdentifier(): string
    {
        return 'fediverse-delivery';
    }

    #[Override]
    public function getCadence(): Cadence
    {
        return new Cadence('*/5 * * * *', offloaded: true);
    }

    #[Override]
    public function getDescription(): string
    {
        return sprintf(
            'Sends queued fediverse activities to remote inboxes, signed with the sending account key. It runs as soon as something is queued and every five minutes. Each run sends due deliveries in batches of %d at once, up to %d deliveries or %d seconds, and runs again at once when some are still due; a remote server that is down or busy gets the delivery again later, with growing pauses, and after %d attempts it is given up. A delivery the remote server refuses, or one to a blocked server, is given up at once.',
            self::BATCH_SIZE,
            self::TICK_BUDGET,
            self::RUN_SECONDS,
            count(self::RETRY_MINUTES) + 1,
        );
    }

    #[Override]
    public function run(): Result
    {
        try {
            $summary = $this->drain();
        } catch (Throwable $e) {
            return new Result(Status::exception, $e->getMessage());
        }

        $now = $this->clock->now();
        if ($this->repository->hasDue($now, $this->leaseExpiredBefore($now))) {
            $this->trigger->runSoon($this->getIdentifier());
        }

        $message = sprintf(
            'Sent: %d, retried: %d, given up: %d, remaining: %d',
            $summary['sent'],
            $summary['retried'],
            $summary['failed'],
            $this->repository->countPending(),
        );

        return new Result($summary['failed'] > 0 ? Status::warning : Status::ok, $message);
    }

    /** @return array{sent: int, retried: int, failed: int} */
    public function drain(): array
    {
        $startedAt = $this->clock->now()->getTimestamp();
        $summary = ['sent' => 0, 'retried' => 0, 'failed' => 0];
        $handled = 0;
        do {
            $now = $this->clock->now();
            $batch = $this->repository->claimDue(min(self::BATCH_SIZE, self::TICK_BUDGET - $handled), $now, $this->leaseExpiredBefore($now));
            foreach ($this->sendAll($batch) as $outcome) {
                ++$summary[$outcome];
            }
            $this->em->flush();
            $handled += count($batch);
            $isBatchFull = count($batch) === self::BATCH_SIZE;
            $hasTimeLeft = ($this->clock->now()->getTimestamp() - $startedAt) < self::RUN_SECONDS;
        } while ($isBatchFull && $handled < self::TICK_BUDGET && $hasTimeLeft);

        return $summary;
    }

    /**
     * @param list<Delivery> $batch
     * @return list<'sent'|'retried'|'failed'>
     */
    private function sendAll(array $batch): array
    {
        $outcomes = [];
        $started = [];
        foreach ($batch as $delivery) {
            if ($this->isBlocked($delivery->getInbox())) {
                $this->logger->info('Fediverse: a delivery to a blocked server was given up.', ['inbox' => $delivery->getInbox()]);
                $this->em->remove($delivery);
                $outcomes[] = 'failed';
            } else {
                $started[] = [$delivery, $this->start($delivery)];
            }
        }

        foreach ($started as [$delivery, $response]) {
            $outcomes[] = $this->settle($delivery, $this->statusOf($response));
        }

        return $outcomes;
    }

    private function start(Delivery $delivery): ?ResponseInterface
    {
        $headers = $this->httpSignature->sign($delivery->getAccount(), 'POST', $delivery->getInbox(), $delivery->getActivity());

        try {
            return $this->client->request('POST', $delivery->getInbox(), [
                'headers' => $headers,
                'body' => $delivery->getActivity(),
                'timeout' => self::SEND_TIMEOUT_SECONDS,
                'max_duration' => self::SEND_MAX_DURATION_SECONDS,
            ]);
        } catch (ExceptionInterface) {
            return null;
        }
    }

    private function statusOf(?ResponseInterface $response): int
    {
        try {
            return $response?->getStatusCode() ?? 0;
        } catch (ExceptionInterface) {
            return 0;
        }
    }

    /** @return 'sent'|'retried'|'failed' */
    private function settle(Delivery $delivery, int $status): string
    {
        if ($status >= 200 && $status < 300) {
            $this->em->remove($delivery);

            return 'sent';
        }

        $refused = $status >= 400 && $status < 500 && !in_array($status, self::RETRYABLE_CLIENT_ERRORS, true);
        $pause = self::RETRY_MINUTES[$delivery->getAttempts()] ?? null;
        if ($refused || $pause === null) {
            $this->logger->info('Fediverse: a delivery was given up.', [
                'inbox' => $delivery->getInbox(),
                'status' => $status,
                'attempts' => $delivery->getAttempts() + 1,
            ]);
            $this->em->remove($delivery);

            return 'failed';
        }

        $delivery->retryAt($this->clock->now()->modify(sprintf('+%d minutes', $pause)));

        return 'retried';
    }

    private function leaseExpiredBefore(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify(sprintf('-%d seconds', self::LEASE_SECONDS));
    }

    private function isBlocked(string $inbox): bool
    {
        $host = parse_url($inbox, PHP_URL_HOST);

        return is_string($host) && $this->configService->getCeiling()->blocks($host);
    }
}
