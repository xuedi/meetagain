<?php declare(strict_types=1);

namespace App\Service;

use App\CronTaskInterface;
use App\Repository\ImageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;

readonly class CleanupService implements CronTaskInterface
{
    public function __construct(
        private ImageRepository $imageRepo,
        private UserRepository $userRepo,
        private EntityManagerInterface $entityManager,
    ) {}

    public function runCronTask(OutputInterface $output): void
    {
        $output->write('Clean image cache ... ');
        $this->removeImageCache();
        $output->writeln('OK');

        $output->write('Clean registrations ... ');
        $this->removeGhostedRegistrations();
        $output->writeln('OK');
    }

    public function removeImageCache(): void
    {
        $images = $this->imageRepo->getOldImageUpdates(30);
        foreach ($images as $image) {
            $image->setUpdatedAt(null);
            $this->entityManager->persist($image);
        }
        $this->entityManager->flush();
    }

    public function removeGhostedRegistrations(): void
    {
        $users = $this->userRepo->getOldRegistrations(10);
        foreach ($users as $user) {
            $activities = $user->getActivities();
            foreach ($activities as $activity) {
                $this->entityManager->remove($activity);
            }
            $this->entityManager->remove($user);
        }
        $this->entityManager->flush();
    }
}
