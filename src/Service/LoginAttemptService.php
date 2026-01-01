<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\LoginAttempt;
use App\Entity\User;
use App\Repository\LoginAttemptRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

readonly class LoginAttemptService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoginAttemptRepository $repo,
    ) {
    }

    public function log(User $user, string $ip, bool $successful, ?string $userAgent = null): void
    {
        $attempt = new LoginAttempt();
        $attempt->setUser($user);
        $attempt->setAttemptedAt(new DateTimeImmutable());
        $attempt->setSuccessful($successful);
        $attempt->setIp($ip);
        $attempt->setUserAgent($userAgent);

        $this->em->persist($attempt);
        $this->em->flush();
    }

    /**
     * Get stats for dashboard.
     *
     * @return array{total: int, successful: int, failed: int}
     */
    public function getStats(int $hours = 24): array
    {
        $since = new DateTimeImmutable("-{$hours} hours");

        return $this->repo->getStats($since);
    }

    /**
     * Get failed attempts count for a user.
     */
    public function getFailedAttemptsCount(User $user, int $minutes = 15): int
    {
        $since = new DateTimeImmutable("-{$minutes} minutes");

        return $this->repo->getFailedAttemptsCount($user, $since);
    }
}
