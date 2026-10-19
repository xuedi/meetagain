<?php declare(strict_types=1);

namespace App\Service\System;

use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Portability\Importer;
use App\Repository\ImageRepository;
use App\Repository\IncidentRepository;
use App\Repository\SupportRequestRepository;
use App\Repository\UserRepository;
use App\Service\Security\MeasureLogger;
use App\Service\Security\MeasureSettings;
use App\Service\Support\ThreadService;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class CleanupService implements TaskInterface
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
        private FilesystemOperator $privateStorage,
    ) {}

    public function getIdentifier(): string
    {
        return 'cleanup';
    }

    public function getCadence(): Cadence
    {
        return new Cadence('5 * * * *', jitterSeconds: 600, offloaded: true);
    }

    public function getDescription(): string
    {
        return sprintf(
            'Hourly housekeeping. It drops the cache-busting version from image URLs whose image last changed more than 30 days ago, deletes registrations not confirmed within %d days, resolves support threads without activity for %d days, clears expired support email verifications, deletes uploaded import archives left unimported for %d hours, and purges security measure logs past their retention and security incidents that ended more than %d days ago. The run message counts each step.',
            self::GHOSTED_REGISTRATION_DAYS,
            self::SUPPORT_THREAD_STALE_DAYS,
            self::PENDING_IMPORT_MAX_HOURS,
            self::INCIDENT_RETENTION_DAYS,
        );
    }

    public function run(): Result
    {
        try {
            $imageCount = $this->removeImageCache();
            $this->logger->info('Image cache cleaned', ['count' => $imageCount]);

            $regCount = $this->removeGhostedRegistrations();
            $this->logger->info('Ghosted registrations removed', ['count' => $regCount]);

            $autoResolvedCount = $this->autoResolveStaleSupportThreads();
            $this->logger->info('Stale support threads auto-resolved', ['count' => $autoResolvedCount]);

            $verifyCount = $this->expireSupportEmailVerifications();
            $this->logger->info('Expired support email verifications cleared', ['count' => $verifyCount]);

            $importCount = $this->removeStaleImportArchives();
            $this->logger->info('Stale import archives removed', ['count' => $importCount]);

            $measureLogCount = $this->removeExpiredSecurityMeasureLogs();
            $this->logger->info('Expired security measure logs removed', ['count' => $measureLogCount]);

            $incidentCount = $this->removeExpiredIncidents();
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

            return new Result(Status::ok, $message);
        } catch (Throwable $e) {
            return new Result(Status::exception, $e->getMessage());
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
        foreach ($this->privateStorage->listContents(Importer::PENDING_DIRECTORY, false) as $item) {
            if (!$item instanceof FileAttributes || !str_ends_with($item->path(), '.zip')) {
                continue;
            }
            $modifiedAt = $item->lastModified() ?? $this->privateStorage->lastModified($item->path());
            if ($modifiedAt < $cutoff) {
                $this->privateStorage->delete($item->path());
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
