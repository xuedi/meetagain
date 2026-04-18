<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\DataFixtures;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Plugin\Dinnerclub\Entity\Dish;
use Plugin\Dinnerclub\Entity\DishTranslation;

class DishSuggestionFixture extends Fixture implements FixtureGroupInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}

    public static function getGroups(): array
    {
        return ['plugin'];
    }

    public function load(ObjectManager $manager): void
    {
        echo 'Creating pending dish suggestion ... ';

        $user = $this->userRepository->findOneBy(['name' => 'Abraham Baker']);
        if ($user === null) {
            echo 'SKIP (user not found)' . PHP_EOL;

            return;
        }

        $dish = new Dish();
        $dish->setApproved(false);
        $dish->setCreatedBy($user->getId());
        $dish->setCreatedAt(new DateTimeImmutable());

        $translation = new DishTranslation();
        $translation->setLanguage('en');
        $translation->setName('Suggested Noodle Dish');
        $translation->setDescription('A delicious noodle dish suggested by a member.');
        $dish->addTranslation($translation);

        $manager->persist($dish);
        $manager->flush();

        echo 'OK' . PHP_EOL;
    }
}
