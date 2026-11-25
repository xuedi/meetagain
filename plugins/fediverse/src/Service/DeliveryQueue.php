<?php declare(strict_types=1);

namespace Plugin\Fediverse\Service;

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
use Throwable;

readonly class DeliveryQueue implements TaskInterface
{
    public const int TICK_BUDGET = 100;
    public const array RETRY_MINUTES = [1, 5, 30, 120, 360, 720, 1440, 1440];

    private const int LEASE_SECONDS = 600;
    private const array RETRYABLE_CLIENT_ERRORS = [408, 429];

    public function __construct(
        private DeliveryRepository $repository,
        private HttpSignature $httpSignature,
        #[Autowire(service: 'remote.client')]
        private HttpClientInterface $client,
        private EntityManagerInterface $em,
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
        if ($inboxes === []) {
            return;
        }

        $json = json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        foreach (array_unique($inboxes) as $inbox) {
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
            'Sends queued fediverse activities to remote inboxes, signed with the sending account key. It runs as soon as something is queued and every five minutes, sending up to %d due deliveries per run; a remote server that is down or busy gets the delivery again later, with growing pauses, and after %d attempts it is given up. A delivery the remote server refuses is given up at once.',
            self::TICK_BUDGET,
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

        if (array_sum($summary) === self::TICK_BUDGET) {
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
        $now = $this->clock->now();
        $summary = ['sent' => 0, 'retried' => 0, 'failed' => 0];
        foreach ($this->repository->claimDue(self::TICK_BUDGET, $now, $now->modify(sprintf('-%d seconds', self::LEASE_SECONDS))) as $delivery) {
            ++$summary[$this->send($delivery)];
            $this->em->flush();
        }

        return $summary;
    }

    /** @return 'sent'|'retried'|'failed' */
    private function send(Delivery $delivery): string
    {
        try {
            $headers = $this->httpSignature->sign($delivery->getAccount(), 'POST', $delivery->getInbox(), $delivery->getActivity());
            $status = $this->client->request('POST', $delivery->getInbox(), ['headers' => $headers, 'body' => $delivery->getActivity()])->getStatusCode();
        } catch (ExceptionInterface) {
            $status = 0;
        }

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
}
