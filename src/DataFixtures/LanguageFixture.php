<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ImageType;
use App\Entity\Language;
use App\Service\ImageService;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class LanguageFixture extends AbstractFixture implements FixtureGroupInterface, DependentFixtureInterface
{
    // Keep constants for backward compatibility in other fixtures
    public const string ENGLISH = 'en';
    public const string GERMAN = 'de';
    public const string CHINESE = 'cn';

    public function __construct(
        private readonly ImageService $imageService,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->start();
        $importUser = $this->getRefUser(SystemUserFixture::IMPORT);

        $languages = [
            ['code' => self::ENGLISH, 'name' => 'English', 'sortOrder' => 1, 'image' => 'en.jpg'],
            ['code' => self::GERMAN, 'name' => 'German', 'sortOrder' => 2, 'image' => 'de.jpg'],
            ['code' => self::CHINESE, 'name' => 'Chinese', 'sortOrder' => 3, 'image' => 'cn.jpg'],
        ];

        foreach ($languages as $data) {
            $language = new Language();
            $language->setCode($data['code']);
            $language->setName($data['name']);
            $language->setEnabled(true);
            $language->setSortOrder($data['sortOrder']);

            // Upload tile image if exists
            $imageFile = __DIR__ . '/Language/' . $data['image'];
            if (file_exists($imageFile)) {
                $uploadedImage = new UploadedFile($imageFile, $data['image'], null, null, true);
                $image = $this->imageService->upload($uploadedImage, $importUser, ImageType::LanguageTile);
                $this->imageService->createThumbnails($image);
                $language->setTileImage($image);
            }

            $manager->persist($language);
            $this->tick();
        }

        $manager->flush();
        $this->stop();
    }

    public function getDependencies(): array
    {
        return [
            UserFixture::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['install'];
    }
}
