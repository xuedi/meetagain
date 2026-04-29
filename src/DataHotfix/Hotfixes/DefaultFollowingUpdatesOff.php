<?php declare(strict_types=1);

namespace App\DataHotfix\Hotfixes;

use App\DataHotfix\DataHotfixInterface;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class DefaultFollowingUpdatesOff implements DataHotfixInterface
{
    private const int BATCH_SIZE = 200;

    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return '2026_04_30_default_following_updates_off';
    }

    #[Override]
    public function execute(): void
    {
        $i = 0;
        foreach ($this->userRepository->iterateAll() as $user) {
            $settings = $user->getNotificationSettings();
            if ($settings->followingUpdates === false) {
                continue;
            }
            $settings->followingUpdates = false;
            $user->setNotificationSettings($settings);

            if (++$i % self::BATCH_SIZE === 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }
        $this->em->flush();
    }
}
