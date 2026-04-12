<?php declare(strict_types=1);

namespace App\Service\Email\Provider;

use App\CronTaskInterface;
use App\Enum\CronTaskStatus;
use App\Repository\EmailQueueRepository;
use App\ValueObject\CronTaskResult;
use App\Service\Config\ConfigService;
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

    public function getIdentifier(): string
    {
        return 'email-delivery-sync';
    }

    public function runCronTask(OutputInterface $output): CronTaskResult
    {
        try {
            if (!$this->configService->isEmailDeliverySyncEnabled()) {
                return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, 'disabled');
            }

            $result = $this->syncPending(100);

            if (!$result->available) {
                return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, 'provider unavailable');
            }

            $message = sprintf('%d/%d synced', $result->updated, $result->checked);
            $output->writeln('EmailDeliveryStatusSyncService: ' . $message);

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, $message);
        } catch (\Throwable $e) {
            $output->writeln('EmailDeliveryStatusSyncService exception: ' . $e->getMessage());

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::exception, $e->getMessage());
        }
    }
}
