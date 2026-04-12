<?php declare(strict_types=1);

namespace App\Service\System;

use App\CronTaskInterface;
use App\EntityActionDispatcher;
use App\Enum\CronTaskStatus;
use App\Enum\EntityAction;
use App\Repository\ImageRepository;
use App\Repository\UserRepository;
use App\ValueObject\CronTaskResult;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

readonly class CleanupService implements CronTaskInterface
{
    public function __construct(
        private ImageRepository $imageRepo,
        private UserRepository $userRepo,
        private EntityManagerInterface $entityManager,
        private EntityActionDispatcher $entityActionDispatcher,
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

            $message = sprintf('image_cache: %d, registrations: %d', $imageCount, $regCount);

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
