<?php declare(strict_types=1);

namespace App\Service\System;

use App\CronTaskInterface;
use App\EntityActionDispatcher;
use App\Enum\CronTaskStatus;
use App\Enum\EntityAction;
use App\ExtendedFilesystem;
use App\Repository\ImageRepository;
use App\Repository\IncidentRepository;
use App\Repository\SupportRequestRepository;
use App\Repository\UserRepository;
use App\Service\Security\MeasureLogger;
use App\Service\Security\MeasureSettings;
use App\Service\Support\ThreadService;
use App\ValueObject\CronTaskResult;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

readonly class CleanupService implements CronTaskInterface
{
    public const int SUPPORT_THREAD_STALE_DAYS = 180;
    public const int GHOSTED_REGISTRATION_DAYS = 10;
    public const int PENDING_IMPORT_MAX_HOURS = 24;
    public const int INCIDENT_RETENTION_DAYS = 180;

    public function __construct(
        private ImageRepository $imageRepo,
        private UserRepository $userRepo,
        private SupportRequestRepository $supportRequestRepo,
        private IncidentRepository $incidentRepo,
        private ThreadService $threadService,
        private MeasureLogger $measureLogger,
        private MeasureSettings $measureSettings,
        private EntityManagerInterface $entityManager,
        private EntityActionDispatcher $entityActionDispatcher,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private ExtendedFilesystem $fs,
        #[Autowire('%kernel.project_dir%/var/import')]
        private string $pendingImportDir,
    ) {}

    public function getIdentifier(): string
    {
        return 'cleanup';
    }

    public function runCronTask(OutputInterface $output): CronTaskResult
    {
        try {
            $imageCount = $this->removeImageCache();
            $output->writeln('Clean image cache: ' . $imageCount);
            $this->logger->info('Image cache cleaned', ['count' => $imageCount]);

            $regCount = $this->removeGhostedRegistrations();
            $output->writeln('Clean registrations: ' . $regCount);
            $this->logger->info('Ghosted registrations removed', ['count' => $regCount]);

            $autoResolvedCount = $this->autoResolveStaleSupportThreads();
            $output->writeln('Auto-resolve support threads: ' . $autoResolvedCount);
            $this->logger->info('Stale support threads auto-resolved', ['count' => $autoResolvedCount]);

            $verifyCount = $this->expireSupportEmailVerifications();
            $output->writeln('Expire support email verifications: ' . $verifyCount);
            $this->logger->info('Expired support email verifications cleared', ['count' => $verifyCount]);

            $importCount = $this->removeStaleImportArchives();
            $output->writeln('Remove stale import archives: ' . $importCount);
            $this->logger->info('Stale import archives removed', ['count' => $importCount]);

            $measureLogCount = $this->removeExpiredSecurityMeasureLogs();
            $output->writeln('Remove expired security measure logs: ' . $measureLogCount);
            $this->logger->info('Expired security measure logs removed', ['count' => $measureLogCount]);

            $incidentCount = $this->removeExpiredIncidents();
            $output->writeln('Remove expired security incidents: ' . $incidentCount);
            $this->logger->info('Expired security incidents removed', ['count' => $incidentCount]);

            $message = sprintf(
                'image_cache: %d, registrations: %d, support_threads_auto_resolved: %d, support_email_verifications_expired: %d, import_archives: %d, security_measure_logs: %d, security_incidents: %d',
                $imageCount,
                $regCount,
                $autoResolvedCount,
                $verifyCount,
                $importCount,
                $measureLogCount,
                $incidentCount,
            );

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, $message);
        } catch (Throwable $e) {
            $output->writeln('CleanupService exception: ' . $e->getMessage());

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::exception, $e->getMessage());
        }
    }

    public function removeExpiredSecurityMeasureLogs(): int
    {
        return $this->measureLogger->purgeOlderThan($this->measureSettings->logRetentionDays());
    }

    public function removeExpiredIncidents(): int
    {
        return $this->incidentRepo->deleteEndedBefore($this->clock->now()->modify(sprintf('-%d days', self::INCIDENT_RETENTION_DAYS)));
    }

    public function removeImageCache(): int
    {
        $count = 0;
        $images = $this->imageRepo->getOldImageUpdates(30);
        foreach ($images as $image) {
            $image->setUpdatedAt(null);
            $this->entityManager->persist($image);
            $count++;
        }
        $this->entityManager->flush();

        return $count;
    }

    public function autoResolveStaleSupportThreads(): int
    {
        $cutoff = $this->clock->now()->modify(sprintf('-%d days', self::SUPPORT_THREAD_STALE_DAYS));

        $count = 0;
        foreach ($this->supportRequestRepo->findStaleUnresolved($cutoff) as $request) {
            $this->threadService->resolve($request);
            $count++;
        }

        return $count;
    }

    public function expireSupportEmailVerifications(): int
    {
        $count = 0;
        foreach ($this->supportRequestRepo->findExpiredEmailVerifications($this->clock->now()) as $request) {
            $this->threadService->clearEmailVerification($request);
            $count++;
        }
        $this->entityManager->flush();

        return $count;
    }

    public function removeStaleImportArchives(): int
    {
        $cutoff = $this->clock->now()->modify(sprintf('-%d hours', self::PENDING_IMPORT_MAX_HOURS))->getTimestamp();

        $count = 0;
        foreach ($this->fs->glob($this->pendingImportDir . '/*.zip') as $file) {
            $modifiedAt = $this->fs->getFileModifiedTime($file);
            if ($modifiedAt !== false && $modifiedAt < $cutoff && $this->fs->deleteFile($file)) {
                $count++;
            }
        }

        return $count;
    }

    public function removeGhostedRegistrations(): int
    {
        $count = 0;
        $users = $this->userRepo->getOldRegistrations(self::GHOSTED_REGISTRATION_DAYS);
        foreach ($users as $user) {
            $this->entityActionDispatcher->dispatch(EntityAction::DeleteUser, $user->getId());
            $activities = $user->getActivities();
            foreach ($activities as $activity) {
                $this->entityManager->remove($activity);
            }
            $this->entityManager->remove($user);
            $count++;
        }
        $this->entityManager->flush();

        return $count;
    }
}
