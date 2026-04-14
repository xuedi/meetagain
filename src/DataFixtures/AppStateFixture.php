<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AppState;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class AppStateFixture extends AbstractFixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $this->start();
        $now = new DateTimeImmutable('now');
        foreach ($this->getData() as [$key, $value]) {
            $entry = new AppState($key, $value, $now);
            $manager->persist($entry);
        }
        $manager->flush();
        $this->stop();
    }

    public static function getGroups(): array
    {
        return ['install'];
    }

    private function getData(): array
    {
        return [
            ['footer_col1_title', 'Help'],
            ['footer_col2_title', 'Platform'],
            ['footer_col3_title', 'Social'],
            ['footer_col4_title', 'Legal'],
        ];
    }
}
