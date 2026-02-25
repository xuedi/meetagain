<?php declare(strict_types=1);

namespace App\Service;

use App\CronTaskInterface;
use App\Enum\EntityAction;
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
        private EntityActionDispatcher $entityActionDispatcher,
    ) {}

    public function runCronTask(OutputInterface $output): void
    {
        $count = $this->removeImageCache();
        $output->writeln('Clean image cache: ' . $count);

        $count = $this->removeGhostedRegistrations();
        $output->writeln('Clean registrations: ' . $count);
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
