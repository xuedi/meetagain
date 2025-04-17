<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use App\Entity\Event;
use App\Entity\User;
use App\Entity\UserActivity;
use App\Repository\ActivityRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class ActivityService
{
    public function __construct(
        private GlobalService $globalService,
        private EntityManagerInterface $em,
        private ActivityRepository $repo)
    {
    }

    public function log(UserActivity $type, User $user, array $meta): void
    {
        $activity = new Activity();
        $activity->setCreatedAt(new DateTimeImmutable());
        $activity->setUser($user);
        $activity->setType($type);
        $activity->setMessage(''); // TODO: remove
        $activity->setVisible($this->isVisible($type));
        $activity->setMeta($meta);

        $this->em->persist($activity);
        $this->em->flush();
    }

    public function getUserList(User $user): array
    {
        return $this->prepareActivityList($this->repo->findBy(['user' => $user]));
    }

    public function getAdminList(): array
    {
        return $this->prepareActivityList($this->repo->findAll());
    }

    private function isVisible(UserActivity $type): bool
    {
        /* Private events, for admin only:
         * UserActivity::ChangedUsername
         */
        return match ($type->value) {
            UserActivity::Login->value => true,
            UserActivity::RsvpYes->value => true,
            UserActivity::RsvpNo->value => true,
            default => false,
        };
    }

    // TODO: Translate messages
    private function prepareActivityList(array $list): array
    {
        $cachedUserName = $this->em->getRepository(User::class)->getUserNameList();
        $cachedEventName = $this->em->getRepository(Event::class)->getEventNameList($this->globalService->getCurrentLocale());

        $preparedList = [];
        foreach ($list as $activity) {
            $type = $activity->getType();
            $meta = $activity->getMeta();
            $msg = match ($type->value) {
                UserActivity::Login->value => "User logged in",
                UserActivity::Registered->value => "User registered",
                UserActivity::RsvpYes->value => sprintf('Going to event: %s', $cachedEventName[$meta['event_id']]),
                UserActivity::RsvpNo->value => sprintf('Is skipping event: %s', $cachedEventName[$meta['event_id']]),
                UserActivity::FollowedUser->value => sprintf('Started following: %s', $cachedUserName[$meta['user_id']]),
                UserActivity::ChangedUsername->value => sprintf('Changed username from %s to %s', $meta['old'], $meta['new']),
                default => '',
            };

            $preparedList[] = $activity->setMessage($msg);
        }

        return $preparedList;
    }
}
