<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\UserStatus;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SystemUserFixture extends AbstractFixture implements FixtureGroupInterface
{
    public const string IMPORT = 'import';
    public const string CRON = 'cron';

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->start();
        foreach ([self::IMPORT, self::CRON] as $userName) {
            $user = new User();
            $user->setVerified(true);
            $user->setStatus(UserStatus::Active);
            $user->setName($userName);
            $user->setEmail($userName . '@example.com');
            $user->setPassword($this->hasher->hashPassword($user, random_bytes(128)));
            $user->setPublic(false);
            $user->setTagging(false);
            $user->setRestricted(false);
            $user->setNotification(false);
            $user->setBio(null);
            $user->setOsmConsent(false);
            $user->setLocale(LanguageFixture::ENGLISH);
            $user->setRoles(['ROLE_SYSTEM']);
            $user->setCreatedAt(new DateTimeImmutable());
            $user->setLastLogin(new DateTime());

            $manager->persist($user);
            $this->addRefUser($userName, $user);
        }
        $manager->flush();
        $this->stop();
    }

    public static function getGroups(): array
    {
        return ['install'];
    }
}
