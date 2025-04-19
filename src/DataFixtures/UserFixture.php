<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\UserStatus;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class UserFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        foreach ($this->getData() as [$locale, $name, $email, $password, $roles, $isVerified, $status]) {
            $user = new User();
            $user->setVerified($isVerified);
            $user->setStatus($status);
            $user->setName($name);
            $user->setEmail($email);
            $user->setPassword($password);
            $user->setPublic(true);
            $user->setRestricted(false);
            $user->setOsmConsent(false);
            $user->setStatus(UserStatus::Active);
            $user->setLocale($locale);
            $user->setRoles($roles);
            $user->setCreatedAt(new DateTimeImmutable());
            $user->setLastLogin(new DateTime());

            $manager->persist($user);

            $this->addReference('user_' . md5((string)$name), $user);
        }
        $manager->flush();

        //$xuedi = $this->getReference('user_' . md5((string) 'xuedi'));
        //$xuedi->addFollower($this->getReference('user_' . md5((string) 'yimu')));
        //$xuedi->addFollower($this->getReference('user_' . md5((string) 'xiaolong')));
        //$xuedi->addFollower($this->getReference('user_' . md5((string) 'user_a')));
        //$xuedi->addFollower($this->getReference('user_' . md5((string) 'user_b')));
        //$xuedi->addFollowing($this->getReference('user_' . md5((string) 'xiaolong')));
        //$xuedi->addFollowing($this->getReference('user_' . md5((string) 'yimu')));
        //$xuedi->addFollowing($this->getReference('user_' . md5((string) 'user_c')));
        //$xuedi->addFollowing($this->getReference('user_' . md5((string) 'import')));
        //$manager->persist($xuedi);
        //$manager->flush();
    }

    private function getData(): array
    {
        $fixedUsers = [
            [
                'en',
                'import',
                'system@beijingcode.org',
                '$2y$13$4OCpKLHN5POFsrAek5RmTu6jAKLyz0xp.czPVLl4yffg91RC9u2fG',
                ['ROLE_SYSTEM'],
                false,
                UserStatus::Active,
            ],
            [
                'de',
                'xuedi',
                'admin@beijingcode.org',
                '$2y$13$4OCpKLHN5POFsrAek5RmTu6jAKLyz0xp.czPVLl4yffg91RC9u2fG',
                ['ROLE_USER', 'ROLE_MANAGER', 'ROLE_ADMIN'],
                true,
                UserStatus::Active
            ],
            [
                'en',
                'yimu',
                'yimu@beijingcode.org',
                '$2y$13$4OCpKLHN5POFsrAek5RmTu6jAKLyz0xp.czPVLl4yffg91RC9u2fG',
                ['ROLE_USER', 'ROLE_MANAGER', 'ROLE_ADMIN'],
                true,
                UserStatus::Active
            ],
            [
                'cn',
                'Crystal Liu',
                'Crystal Liu@beijingcode.org',
                '$2y$13$4OCpKLHN5POFsrAek5RmTu6jAKLyz0xp.czPVLl4yffg91RC9u2fG',
                ['ROLE_USER', 'ROLE_MANAGER'],
                true,
                UserStatus::Active
            ],
        ];

        $files = glob(__DIR__ . "/Avatars/*.jpg");
        foreach ($files as $image) {
            $chunks = explode("/", $image);
            $name = str_replace('.jpg', '', end($chunks));
            if (in_array($name, ['import', 'xuedi', 'yimu', 'Crystal Liu'])) {
                continue;
            }
            $fixedUsers[] = [
                'en',
                $name,
                "$name@beijingcode.org",
                '$2y$13$4OCpKLHN5POFsrAek5RmTu6jAKLyz0xp.czPVLl4yffg91RC9u2fG',
                ['ROLE_USER'],
                false,
                UserStatus::Active,
            ];
        }

        return $fixedUsers;
    }
}
