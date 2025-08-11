<?php declare(strict_types=1);

namespace Plugin\Dishes\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Plugin\Dishes\Entity\Dish;

class DishFixture extends Fixture implements FixtureGroupInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating dishes ... ';
        foreach ($this->getData() as [$name]) {
            $dish = new Dish();
            $dish->setName($name);

            $manager->persist($dish);
        }
        $manager->flush();
        echo 'OK' . PHP_EOL;
    }

    private function getData(): array
    {
        return [
            [
                'Fish & chips',
            ],
            [
                'French fries',
            ],
            [
                'Hamburger',
            ],
        ];
    }

    public static function getGroups(): array
    {
        return ['Dishes'];
    }
}
