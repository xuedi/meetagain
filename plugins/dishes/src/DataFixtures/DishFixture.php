<?php declare(strict_types=1);

namespace Plugin\Dishes\DataFixtures;

use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Plugin\Dishes\Entity\Dish;
use Plugin\Dishes\Entity\DishTranslation;

class DishFixture extends Fixture implements FixtureGroupInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating dishes ... ';
        foreach ($this->getData() as [$number, $translations]) {
            $dish = new Dish();
            $dish->setApproved(true);
            $dish->setCreatedBy(1);
            $dish->setCreatedAt(new DateTimeImmutable());

            foreach ($translations as $language => $data) {
                $translation = new DishTranslation();
                $translation->setLanguage($language);
                $translation->setName($data['name']);
                $translation->setPhonetic($data['phonetic']);
                $translation->setDescription($data['description']);
                $translation->setDish($dish);

                $manager->persist($translation);
                $dish->addTranslation($translation);
            }

            $manager->persist($dish);
        }
        $manager->flush();
        echo 'OK' . PHP_EOL;
    }

    private function getData(): array
    {
        return [
            [
                '1.jpg',
                [
                    'cn' => [
                        'name' => '炒饼',
                        'phonetic' => 'Chǎo bǐng',
                        'description' => 'Description cn-1',
                    ],
                    'en' => [
                        'name' => 'Stir-fried pancakes',
                        'phonetic' => null,
                        'description' => 'Description en-1',
                    ],
                    'de' => [
                        'name' => 'Gebratener Fladen',
                        'phonetic' => null,
                        'description' => 'Description de-1',
                    ],
                ]
            ],
            [
                '2.jpg',
                [
                    'cn' => [
                        'name' => 'Dish 2cn',
                        'phonetic' => 'Dish 2',
                        'description' => 'Description cn-1',
                    ],
                    'en' => [
                        'name' => 'Dish 2en',
                        'phonetic' => null,
                        'description' => 'Description en-1',
                    ],
                    'de' => [
                        'name' => 'Dish 2de',
                        'phonetic' => null,
                        'description' => 'Description de-1',
                    ],
                ]
            ],
            [
                '3.jpg',
                [
                    'cn' => [
                        'name' => 'Dish 3cn',
                        'phonetic' => 'Dish 3',
                        'description' => 'Description cn-1',
                    ],
                    'en' => [
                        'name' => 'Dish 3en',
                        'phonetic' => null,
                        'description' => 'Description en-1',
                    ],
                    'de' => [
                        'name' => 'Dish 3de',
                        'phonetic' => null,
                        'description' => 'Description de-1',
                    ],
                ]
            ],
        ];
    }

    public static function getGroups(): array
    {
        return ['Dishes'];
    }
}
