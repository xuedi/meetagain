<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\DataFixtures;

use App\Repository\ImageRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Plugin\Dinnerclub\Entity\DishImageSuggestion;
use Plugin\Dinnerclub\Enum\DishImageSuggestionType;
use Plugin\Dinnerclub\Repository\DishRepository;

class DishImageSuggestionFixture extends Fixture implements FixtureGroupInterface
{
    public function __construct(
        private readonly DishRepository $dishRepository,
        private readonly ImageRepository $imageRepository,
        private readonly UserRepository $userRepository,
    ) {}

    public static function getGroups(): array
    {
        return ['plugin'];
    }

    public function load(ObjectManager $manager): void
    {
        echo 'Creating pending dish image suggestion ... ';

        $dishes = $this->dishRepository->findApproved();
        if ($dishes === []) {
            echo 'SKIP (no approved dishes found)' . PHP_EOL;

            return;
        }

        $images = $this->imageRepository->findBy([], ['id' => 'ASC'], 1);
        if ($images === []) {
            echo 'SKIP (no images found)' . PHP_EOL;

            return;
        }

        $user = $this->userRepository->findOneBy(['name' => 'Abraham Baker']);
        if ($user === null) {
            echo 'SKIP (user not found)' . PHP_EOL;

            return;
        }

        $suggestion = new DishImageSuggestion();
        $suggestion->setDish($dishes[0]);
        $suggestion->setImage($images[0]);
        $suggestion->setType(DishImageSuggestionType::AddImage);
        $suggestion->setSuggestedBy($user->getId());
        $suggestion->setCreatedAt(new DateTimeImmutable());

        $manager->persist($suggestion);
        $manager->flush();

        echo 'OK' . PHP_EOL;
    }
}
