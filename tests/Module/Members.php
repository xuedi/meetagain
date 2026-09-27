<?php declare(strict_types=1);

namespace Tests\Module;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class Members
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function member(string $name): User
    {
        return $this->persist($name, UserRole::User);
    }

    public function admin(string $name): User
    {
        return $this->persist($name, UserRole::Admin);
    }

    private function persist(string $name, UserRole $role): User
    {
        $user = new User()
            ->setName($name)
            ->setEmail(strtolower($name) . '@module-test.example')
            ->setPassword('not-a-hash')
            ->setRole($role)
            ->setStatus(UserStatus::Active)
            ->setVerified(true)
            ->setNotification(true)
            ->setCreatedAt(new DateTimeImmutable())
            ->setLastLogin(new DateTime());
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
