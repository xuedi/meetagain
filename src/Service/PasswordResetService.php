<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\ActivityType;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

readonly class PasswordResetService
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private ActivityService $activityService,
        private EmailService $emailService,
    ) {}

    public function requestReset(string $email): ?User
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if ($user === null) {
            return null;
        }

        $user->setRegcode($this->generateResetToken());
        $this->em->persist($user);
        $this->em->flush();

        $this->activityService->log(ActivityType::PasswordResetRequest, $user);
        $this->emailService->prepareResetPassword($user);
        $this->emailService->sendQueue();

        return $user;
    }

    public function findUserByResetCode(string $code): ?User
    {
        return $this->userRepository->findOneBy(['regcode' => $code]);
    }

    public function resetPassword(User $user, string $newPassword): void
    {
        $user->setPassword($this->hasher->hashPassword($user, $newPassword));
        $user->setRegcode(null);

        $this->em->persist($user);
        $this->em->flush();

        $this->activityService->log(ActivityType::PasswordReset, $user);
    }

    private function generateResetToken(): string
    {
        return sha1(random_bytes(128));
    }
}
