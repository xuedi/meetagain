<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use App\Entity\ActivityType;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Service\Activity\MessageFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

readonly class ActivityService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ActivityRepository $repo,
        private NotificationService $notificationService,
        private MessageFactory $messageFactory,
    )
    {
    }

    public function log(ActivityType $type, User $user, array $meta = []): void
    {
        $activity = new Activity();
        $activity->setCreatedAt(new DateTimeImmutable());
        $activity->setUser($user);
        $activity->setType($type);
        $activity->setMeta($meta);

        $this->messageFactory->build($activity)->validate();
        $this->notificationService->notify($activity);

        $this->em->persist($activity);
        $this->em->flush();
    }

    public function getUserList(User $user): array
    {
        return $this->prepareActivityList($this->repo->getUserDisplay($user), true);
    }

    public function getAdminList(): array
    {
        return $this->prepareActivityList($this->repo->findBy([], ['createdAt' => 'DESC'], 250));
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
