<?php declare(strict_types=1);

namespace App\Service\Member;

use App\Activity\ActivityService;
use App\Activity\Messages\ChangedUsername;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProfileService
{
    public const int NAME_STORAGE_LIMIT = 64;

    public function __construct(
        private EntityManagerInterface $em,
        private ActivityService $activityService,
    ) {}

    public function update(User $user, string $name, ?string $bio, string $locale, bool $public): void
    {
        $this->rename($user, $name);
        $user->setBio($bio);
        $user->setLocale($locale);
        $user->setPublic($public);

        $this->em->persist($user);
        $this->em->flush();
    }

    public const string NAME_VIOLATION_MESSAGE = 'security.validator_username_max';

    public static function nameLimitExceeded(?string $current, string $name): ?int
    {
        if (mb_strlen($name) > self::NAME_STORAGE_LIMIT) {
            return self::NAME_STORAGE_LIMIT;
        }

        if ($name !== $current && mb_strlen($name) > User::NAME_MAX_LENGTH) {
            return User::NAME_MAX_LENGTH;
        }

        return null;
    }

    private function rename(User $user, string $name): void
    {
        $previous = $user->getName();
        if ($previous === $name) {
            return;
        }

        $this->activityService->log(ChangedUsername::TYPE, $user, ['old' => $previous, 'new' => $name]);
        $user->setName($name);
    }
}
