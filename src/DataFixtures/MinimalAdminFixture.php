<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\UserRole;
use App\Entity\UserStatus;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class MinimalAdminFixture extends AbstractFixture implements FixtureGroupInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $this->start();

        $user = new User();
        $user->setName(UserFixture::ADMIN);
        $user->setEmail(UserFixture::ADMIN . '@example.org');
        $user->setPassword($this->hasher->hashPassword($user, '1234'));
        $user->setRole(UserRole::Admin);
        $user->setStatus(UserStatus::Active);
        $user->setVerified(true);
        $user->setPublic(true);
        $user->setTagging(false);
        $user->setRestricted(false);
        $user->setNotification(false);
        $user->setOsmConsent(false);
        $user->setLocale('en');
        $user->setCreatedAt(new DateTimeImmutable());
        $user->setLastLogin(new DateTime());

        $manager->persist($user);
        $manager->flush();

        $this->addRefUser(UserFixture::ADMIN, $user);
        $this->stop();
    }

    public static function getGroups(): array
    {
        return ['minimal'];
    }
}
