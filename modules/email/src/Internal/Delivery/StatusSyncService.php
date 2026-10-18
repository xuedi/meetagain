<?php declare(strict_types=1);

namespace Module\Email\Internal\Delivery;

use App\Service\Config\ConfigService;
use Doctrine\ORM\EntityManagerInterface;
use Module\Email\Internal\Repository\EmailQueueRepository;
use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class StatusSyncService implements TaskInterface
{
    public function __construct(
        private ProviderChain $provider,
        private EmailQueueRepository $repo,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private ConfigService $configService,
    ) {}

    public function syncPending(int $limit = 100): SyncResult
    {
        if (!$this->provider->isAvailable()) {
            return SyncResult::unavailable();
        }

        $emails = $this->repo->findWithProviderMessageIdAndNoStatus($limit);
        $updated = 0;

        foreach ($emails as $email) {
            $log = $this->provider->getLogByMessageId((string) $email->getProviderMessageId());
            if ($log === null) {
                $this->logger->warning('EmailDeliveryStatusSync: no provider log found for message', [
                    'email_queue_id' => $email->getId(),
                    'provider_message_id' => $email->getProviderMessageId(),
                ]);
                continue;
            }
            $email->setProviderStatus($log->status);
            $updated++;
        }

        $this->em->flush();

        return SyncResult::success($updated, count($emails));
    }

    public function getIdentifier(): string
    {
        return 'email-delivery-sync';
    }

    public function getCadence(): Cadence
    {
        return new Cadence('*/10 * * * *', offloaded: true);
    }

    public function run(): Result
    {
        try {
            if (!$this->configService->isEmailDeliverySyncEnabled()) {
                return new Result(Status::ok, 'disabled');
            }

            $result = $this->syncPending(100);

            if (!$result->available) {
                return new Result(Status::ok, 'provider unavailable');
            }

            $message = sprintf('%d/%d synced', $result->updated, $result->checked);

            return new Result(Status::ok, $message);
        } catch (Throwable $e) {
            return new Result(Status::exception, $e->getMessage());
        }
    }
}
