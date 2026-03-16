<?php declare(strict_types=1);

namespace App\Service\Email\Provider;

use App\CronTaskInterface;
use App\Repository\EmailQueueRepository;
use App\Service\ConfigService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class EmailDeliveryStatusSyncService implements CronTaskInterface
{
    public function __construct(
        private EmailDeliveryProviderInterface $provider,
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
            if ($log !== null) {
                $email->setProviderStatus($log->status);
                $updated++;
            } else {
                $this->logger->warning('EmailDeliveryStatusSync: no provider log found for message', [
                    'email_queue_id' => $email->getId(),
                    'provider_message_id' => $email->getProviderMessageId(),
                ]);
            }
        }

        $this->em->flush();

        return SyncResult::success($updated, count($emails));
    }

    public function runCronTask(OutputInterface $output): void
    {
        if (!$this->configService->isEmailDeliverySyncEnabled()) {
            return;
        }

        $result = $this->syncPending(100);

        if (!$result->available) {
            return;
        }

        $output->writeln(sprintf('EmailDeliveryStatusSyncService: %d/%d synced', $result->updated, $result->checked));
    }
}
