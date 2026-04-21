<?php declare(strict_types=1);

namespace App\Service\Member;

use App\Activity\ActivityService;
use App\Activity\Messages\PasswordReset;
use App\Activity\Messages\PasswordResetRequest;
use App\Emails\Types\PasswordResetEmail;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Email\BlocklistCheckerInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

readonly class PasswordResetService
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private ActivityService $activityService,
        private PasswordResetEmail $passwordResetEmail,
        private BlocklistCheckerInterface $blocklist,
        private LoggerInterface $logger,
    ) {}

    public function requestReset(string $email): ?User
    {
        if ($this->blocklist->isBlocked($email)) {
            $this->logger->info('Password reset refused: email on blocklist', ['email' => $email]);

            return null;
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if ($user === null) {
            return null;
        }

        $user->setRegcode($this->generateResetToken());
        $user->setRegcodeExpiresAt(new DateTimeImmutable('+24 hours'));
        $this->em->persist($user);
        $this->em->flush();

        $this->activityService->log(PasswordResetRequest::TYPE, $user);
        $this->passwordResetEmail->send(['user' => $user]);

        return $user;
    }

    public function findUserByResetCode(string $code): ?User
    {
        $user = $this->userRepository->findOneBy(['regcode' => $code]);

        if ($user === null || $user->isRegcodeExpired()) {
            return null;
        }

        return $user;
    }

    public function resetPassword(User $user, #[SensitiveParameter] string $newPassword): bool
    {
        if ($this->blocklist->isBlocked((string) $user->getEmail())) {
            $this->logger->info('Password reset finalize refused: email on blocklist', [
                'email' => $user->getEmail(),
            ]);

            return false;
        }

        $user->setPassword($this->hasher->hashPassword($user, $newPassword));
        $user->setRegcode(null);
        $user->setRegcodeExpiresAt(null);

        $this->em->persist($user);
        $this->em->flush();

        $this->activityService->log(PasswordReset::TYPE, $user);

        return true;
    }

    private function generateResetToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
