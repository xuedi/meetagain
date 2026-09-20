<?php declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\NotificationSettings;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

readonly class NotificationSettingsService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function toggle(User $user, string $type): bool
    {
        $settings = $user->getNotificationSettings()->toggle($type);
        $this->store($user, $settings);

        return $settings->isActive($type);
    }

    /**
     * @param array<string, bool> $changes
     */
    public function apply(User $user, array $changes): void
    {
        $unknown = NotificationSettings::unknownKeys(array_keys($changes));
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf('Unknown notification settings: %s', implode(', ', $unknown)));
        }

        $settings = $user->getNotificationSettings();
        foreach ($changes as $key => $value) {
            $settings->set($key, $value);
        }

        $this->store($user, $settings);
    }

    /**
     * @param array<string, bool> $categories
     */
    public function applyPush(User $user, array $categories): void
    {
        $settings = $user->getNotificationSettings();
        foreach ($categories as $category => $value) {
            $settings->setPush((string) $category, $value);
        }

        $this->store($user, $settings);
    }

    public function applyQuietHours(User $user, bool $enabled, string $start, string $end, string $timeZone, bool $allowUrgent): void
    {
        $settings = $user->getNotificationSettings()->setQuietHours($enabled, $start, $end, $timeZone, $allowUrgent);
        $this->store($user, $settings);
    }

    public function setMasterSwitch(User $user, bool $enabled): void
    {
        $user->setNotification($enabled);
        $this->em->persist($user);
        $this->em->flush();
    }

    private function store(User $user, NotificationSettings $settings): void
    {
        $user->setNotificationSettings($settings);
        $this->em->persist($user);
        $this->em->flush();
    }
}
