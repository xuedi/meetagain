<?php declare(strict_types=1);

namespace Plugin\Dishes\DataFixtures;

use App\DataFixtures\UserFixture;
use App\Entity\ImageType;
use App\Entity\User;
use App\Service\ImageService;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Plugin\Dishes\Entity\Dish;
use Plugin\Dishes\Entity\DishTranslation;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DishFixture extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public function __construct(
        private readonly ImageService $imageService,
    ) {}

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $importUser = $this->getReference('user_' . md5('import'), User::class);

        echo 'Creating dishes ... ';
        foreach ($this->getData() as [$imagePreview, $translations]) {
            $dish = new Dish();
            $dish->setOriginLang('cn');
            $dish->setApproved(true);
            $dish->setCreatedBy($importUser->getId());
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

            // upload file & thumbnails
            $imageFile = __DIR__ . "/dishes/$imagePreview";
            $uploadedImage = new UploadedFile($imageFile, $imagePreview);
            $image = $this->imageService->upload($uploadedImage, $importUser, ImageType::PluginDishPreview);
            $this->imageService->createThumbnails($image);

            // associate image with a user
            $dish->setPreviewImage($image);

            $manager->persist($dish);
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
        return [
            [
                '1.jpg',
                [
                    'cn' => [
                        'name' => '炒饼',
                        'phonetic' => 'Chǎo bǐng',
                        'description' => 'Description cn',
                    ],
                    'en' => [
                        'name' => 'Stir-fried pancakes',
                        'phonetic' => null,
                        'description' => 'Description en',
                    ],
                    'de' => [
                        'name' => 'Gebratener Fladen',
                        'phonetic' => null,
                        'description' => 'Description de',
                    ],
                ],
            ],
            [
                '2.jpg',
                [
                    'cn' => [
                        'name' => '麻婆豆腐',
                        'phonetic' => 'Má pó dòufu',
                        'description' => 'Description cn',
                    ],
                    'en' => [
                        'name' => 'Mapo tofu',
                        'phonetic' => null,
                        'description' => 'Description en',
                    ],
                    'de' => [
                        'name' => 'Mapo-Tofu',
                        'phonetic' => null,
                        'description' => 'Description de',
                    ],
                ],
            ],
            [
                '3.jpg',
                [
                    'cn' => [
                        'name' => '宫保鸡丁',
                        'phonetic' => 'Gōng bǎo jī dīng',
                        'description' => 'Description cn',
                    ],
                    'en' => [
                        'name' => 'Kung Pao Chicken',
                        'phonetic' => null,
                        'description' => 'Description en',
                    ],
                    'de' => [
                        'name' => 'Kung Pao Hühnchen',
                        'phonetic' => null,
                        'description' => 'Description de',
                    ],
                ],
            ],
        ];
    }

    public static function getGroups(): array
    {
        return ['Dishes'];
    }
}
