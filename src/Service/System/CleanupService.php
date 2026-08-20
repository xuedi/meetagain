<?php declare(strict_types=1);

namespace App\Service\System;

use App\CronTaskInterface;
use App\EntityActionDispatcher;
use App\Enum\CronTaskStatus;
use App\Enum\EntityAction;
use App\Repository\ImageRepository;
use App\Repository\SupportRequestRepository;
use App\Repository\UserRepository;
use App\Service\Support\ThreadService;
use App\ValueObject\CronTaskResult;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

readonly class CleanupService implements CronTaskInterface
{
    public const int SUPPORT_THREAD_STALE_DAYS = 180;

    public function __construct(
        private ImageRepository $imageRepo,
        private UserRepository $userRepo,
        private SupportRequestRepository $supportRequestRepo,
        private ThreadService $threadService,
        private EntityManagerInterface $entityManager,
        private EntityActionDispatcher $entityActionDispatcher,
        private ClockInterface $clock,
        private LoggerInterface $logger,
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

            $message = sprintf(
                'image_cache: %d, registrations: %d, support_threads_auto_resolved: %d, support_email_verifications_expired: %d',
                $imageCount,
                $regCount,
                $autoResolvedCount,
                $verifyCount,
            );

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, $message);
        } catch (\Throwable $e) {
            $output->writeln('CleanupService exception: ' . $e->getMessage());

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::exception, $e->getMessage());
        }
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

    public function removeGhostedRegistrations(): int
    {
        $count = 0;
        $users = $this->userRepo->getOldRegistrations(10);
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
