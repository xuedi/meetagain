<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Config;
use App\Entity\ConfigType;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ConfigFixture extends Fixture implements DependentFixtureInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating config ... ';
        foreach ($this->getData() as [$name, $value, $type]) {
            $user = new Config();
            $user->setName($name);
            $user->setValue($value);
            $user->setType($type);

            $manager->persist($user);
        }
        $manager->flush();
        echo 'OK' . PHP_EOL;
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [
            UserFixture::class,
        ];
    }

    private function getData(): array
    {
        $importUser = $this->getReference('user_' . md5((string) 'import'), User::class);
        return [
            ['pageUrl', 'http://localhost', ConfigType::String],
            ['recurringTargetMonths', '60', ConfigType::Integer],
            ['isOpenRegistration', 'true', ConfigType::Boolean],
            ['isOffline', 'false', ConfigType::Boolean],
            ['systemUser', $importUser->getEmail(), ConfigType::String],
        ];
    }
}
