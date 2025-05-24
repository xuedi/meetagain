<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use App\Entity\Event;
use App\Entity\User;
use App\Entity\ActivityType;
use App\Repository\ActivityRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

readonly class ActivityService
{
    public function __construct(
        private GlobalService $globalService,
        private EntityManagerInterface $em,
        private ActivityRepository $repo
    ) {
    }

    public function log(ActivityType $type, User $user, array $meta = []): void
    {
        $this->checkRequiredMetaData($type, $meta);

        $activity = new Activity();
        $activity->setCreatedAt(new DateTimeImmutable());
        $activity->setUser($user);
        $activity->setType($type);
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
        return $this->prepareActivityList($this->repo->findBy([], ['createdAt' => 'DESC'], 250));
    }

    private function prepareActivityList(array $list): array
    {
        $preparedList = [];
        foreach ($list as $activity) {
            $preparedList[] = $this->prepareActivity($activity);
        }

        return $preparedList;
    }

    // TODO: Translate messages
    public function prepareActivity(Activity $activity): Activity
    {
        $cachedUserName = $this->em->getRepository(User::class)->getUserNameList();
        $cachedEventName = $this->em->getRepository(Event::class)->getEventNameList($this->globalService->getCurrentLocale());

        $meta = $activity->getMeta();
        $msg = match ($activity->getType()->value) {
            ActivityType::Login->value => "User logged in",
            ActivityType::Registered->value => "User registered",
            ActivityType::RsvpYes->value => sprintf('Going to event: %s', $cachedEventName[$meta['event_id']]),
            ActivityType::RsvpNo->value => sprintf('Is skipping event: %s', $cachedEventName[$meta['event_id']]),
            ActivityType::FollowedUser->value => sprintf('Started following: %s', $cachedUserName[$meta['user_id']]),
            ActivityType::ChangedUsername->value => sprintf('Changed username from %s to %s', $meta['old'], $meta['new']),
            default => '',
        };

        $activity->setMessage($msg);

        return $activity;
    }

    private function checkRequiredMetaData(ActivityType $type, array $meta): void
    {
        switch ($type->value) {
            case ActivityType::RsvpYes->value:
                $this->ensureHasKey($meta, 'event_id', $type->name);
                $this->ensureIsNumeric($meta, 'event_id', $type->name);
                break;
            case ActivityType::FollowedUser->value:
                $this->ensureHasKey($meta, 'user_id', $type->name);
                $this->ensureIsNumeric($meta, 'user_id', $type->name);
                break;
            case ActivityType::ChangedUsername->value:
                $this->ensureHasKey($meta, 'old', $type->name);
                $this->ensureHasKey($meta, 'new', $type->name);
                break;
        }
    }

    private function ensureHasKey(array $meta, string $key, string $type): void
    {
        if (!isset($meta[$key])) {
            throw new InvalidArgumentException("Missing '$key' in meta in $type");
        }
    }

    private function ensureIsNumeric(array $meta, string $key, string $type): void
    {
        if (!is_numeric($meta[$key])) {
            throw new InvalidArgumentException("Value '$key' has to be numeric in $type");
        }
    }
}
