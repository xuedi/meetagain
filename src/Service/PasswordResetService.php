<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\ActivityType;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Service for handling password reset operations.
 *
 * Encapsulates the business logic for:
 * - Generating reset tokens
 * - Validating reset codes
 * - Updating passwords
 */
readonly class PasswordResetService
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private ActivityService $activityService,
        private EmailService $emailService,
    ) {
    }

    /**
     * Request a password reset for the given email.
     *
     * Generates a reset token, saves it to the user, and sends
     * a password reset email. Returns the user if found, null otherwise.
     */
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

    /**
     * Find a user by their reset code.
     *
     * Returns null if no user with that code exists.
     */
    public function findUserByResetCode(string $code): ?User
    {
        return $this->userRepository->findOneBy(['regcode' => $code]);
    }

    /**
     * Reset the user's password.
     *
     * Hashes the new password, clears the reset code,
     * and logs the password reset activity.
     */
    public function resetPassword(User $user, string $newPassword): void
    {
        $user->setPassword($this->hasher->hashPassword($user, $newPassword));
        $user->setRegcode(null);

        $this->em->persist($user);
        $this->em->flush();

        $this->activityService->log(ActivityType::PasswordReset, $user);
    }

    /**
     * Generate a secure reset token.
     */
    private function generateResetToken(): string
    {
        return sha1(random_bytes(128));
    }
}
