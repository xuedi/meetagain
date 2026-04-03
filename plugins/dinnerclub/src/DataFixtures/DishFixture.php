<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\DataFixtures;

use App\DataFixtures\AbstractFixture;
use App\Enum\ImageType;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Media\ImageService;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Plugin\Dinnerclub\Entity\Dish;
use Plugin\Dinnerclub\Entity\DishTranslation;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DishFixture extends AbstractFixture implements FixtureGroupInterface
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly UserRepository $userRepository,
    ) {}

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $importUser = $this->userRepository->findOneBy(['email' => 'import@example.com']);
        if (!$importUser instanceof User) {
            echo 'Creating dishes ... SKIP (import user not found)' . PHP_EOL;
            return;
        }

        echo 'Creating dishes ... ';
        foreach ($this->getData() as [$imageFile, $origin, $translations]) {
            $dish = new Dish();
            $dish->setOriginLang('cn');
            $dish->setOrigin($origin);
            $dish->setApproved(true);
            $dish->setCreatedBy($importUser->getId());
            $dish->setCreatedAt(new DateTimeImmutable());

            foreach ($translations as $language => $data) {
                $translation = new DishTranslation();
                $translation->setLanguage($language);
                $translation->setName($data['name']);
                $translation->setPhonetic($data['phonetic']);
                $translation->setDescription($data['description']);
                $dish->addTranslation($translation);
            }

            // upload file & thumbnails if exists
            $imagePath = __DIR__ . "/dinnerclub/$imageFile";
            if (file_exists($imagePath)) {
                $uploadedImage = new UploadedFile($imagePath, $imageFile);
                $image = $this->imageService->upload($uploadedImage, $importUser, ImageType::PluginDishPreview);
                $this->imageService->createThumbnails($image, ImageType::PluginDishPreview);
                $dish->setPreviewImage($image);
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
                'Chengdu, Sichuan',
                [
                    'cn' => [
                        'name' => '麻婆豆腐',
                        'phonetic' => 'má po tofu',
                        'description' => 'Spicy tofu in chili and bean-based sauce. Originated in Chengdu.',
                    ],
                    'en' => [
                        'name' => 'Mapo Tofu',
                        'phonetic' => null,
                        'description' => 'Spicy tofu in chili and bean-based sauce.',
                    ],
                    'de' => [
                        'name' => 'Mapo Tofu',
                        'phonetic' => null,
                        'description' => 'Würziger Tofu in Chili- und Bohnensauce.',
                    ],
                ],
            ],
            [
                '2.jpg',
                'Guizhou, China',
                [
                    'cn' => [
                        'name' => '宫保鸡丁',
                        'phonetic' => 'gōng bǎo jī dīng',
                        'description' => 'Diced chicken stir-fried with vegetables, peanuts, dried chilis and soy sauce.',
                    ],
                    'en' => [
                        'name' => 'Kung Pao Chicken',
                        'phonetic' => null,
                        'description' => 'Diced chicken stir-fried with peanuts and chilis.',
                    ],
                    'de' => [
                        'name' => 'Kung Pao Hühnchen',
                        'phonetic' => null,
                        'description' => 'Gehacktes Hähnchen mit Erdnüssen und Chilis angebraten.',
                    ],
                ],
            ],
            [
                '3.jpg',
                'Shanghai, China',
                [
                    'cn' => [
                        'name' => '红烧肉',
                        'phonetic' => 'hóng shāo ròu',
                        'description' => 'Braised pork belly in soy sauce, a classic Chinese comfort food.',
                    ],
                    'en' => [
                        'name' => 'Braised Pork Belly',
                        'phonetic' => null,
                        'description' => 'Tender pork belly braised in soy sauce.',
                    ],
                    'de' => [
                        'name' => 'Geschmorter Schweinebauch',
                        'phonetic' => null,
                        'description' => 'Zarter Schweinebauch geschmort in Sojasauce.',
                    ],
                ],
            ],
        ];
    }

    public static function getGroups(): array
    {
        return ['plugin'];
    }
}
