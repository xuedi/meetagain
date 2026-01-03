<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use App\Entity\ActivityType;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Service\Activity\MessageFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class ActivityService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ActivityRepository $repo,
        private NotificationService $notificationService,
        private MessageFactory $messageFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function log(ActivityType $type, User $user, array $meta = []): void
    {
        $activity = new Activity();
        $activity->setCreatedAt(new DateTimeImmutable());
        $activity->setUser($user);
        $activity->setType($type);
        $activity->setMeta($meta);

        try {
            $this->messageFactory->build($activity)->validate();
            $this->notificationService->notify($activity);

            $this->em->persist($activity);
            $this->em->flush();
        } catch (Throwable $exception) {
            // TODO: check why this error still persist on uploading images to events
            $this->logger->error('Could not log Activity', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function getUserList(User $user): array
    {
        return $this->prepareActivityList($this->repo->getUserDisplay($user), true);
    }

    public function getAdminList(): array
    {
        return $this->prepareActivityList($this->repo->findBy([], ['createdAt' => 'DESC'], 250));
    }

    /**
     * Validates all activities in the database and returns invalid ones.
     *
     * @return array<array{id: int, type: string, error: string}>
     */
    public function validateAllActivities(): array
    {
        $invalidActivities = [];
        $activities = $this->repo->findAll();

        foreach ($activities as $activity) {
            try {
                $this->messageFactory->build($activity)->validate();
            } catch (Throwable $e) {
                $invalidActivities[] = [
                    'id' => $activity->getId(),
                    'type' => $activity->getType()->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $invalidActivities;
    }

    private function prepareActivityList(array $list, ?bool $asHtml = false): array
    {
        $preparedList = [];
        foreach ($list as $activity) {
            $preparedList[] = $activity->setMessage($this->messageFactory->build($activity)->render($asHtml));
        }

        return $preparedList;
    }
}
