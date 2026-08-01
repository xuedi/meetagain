<?php declare(strict_types=1);

namespace Plugin\Photos\DataFixtures;

use App\DataFixtures\AbstractFixture;
use App\DataFixtures\UserFixture;
use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Override;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Entity\PhotoTranslation;
use Plugin\Photos\Service\ExifService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PhotoFixture extends AbstractFixture implements FixtureGroupInterface
{
    private const string FIXTURE_DIR = __DIR__ . '/../../fixtures';

    public function __construct(
        private readonly ImageService $imageService,
        private readonly ImageLocationService $imageLocationService,
        private readonly ExifService $exifService,
    ) {}

    #[Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating photos ... ';

        $creator = $manager->getRepository(User::class)->findOneBy([
            'email' => str_replace(' ', '.', UserFixture::ADEM_LANE) . '@example.org',
        ]);
        if (!$creator instanceof User) {
            echo 'skipped' . PHP_EOL;

            return;
        }

        $created = [];
        foreach ($this->getData() as [$file, $translations]) {
            $photo = $this->createPhoto($manager, $file, $translations, $creator);
            if ($photo !== null) {
                $created[] = $photo;
            }
        }

        foreach ($created as $photo) {
            $image = $photo->getImage();
            if ($image instanceof Image) {
                $this->imageLocationService->addLocation((int) $image->getId(), ImageType::PluginPhotosPhoto, (int) $photo->getId());
            }
        }

        echo 'OK' . PHP_EOL;
    }

    public static function getGroups(): array
    {
        return ['plugin'];
    }

    /**
     * @param array<string, array<string, string>> $translations
     */
    private function createPhoto(ObjectManager $manager, string $file, array $translations, User $creator): ?Photo
    {
        $path = self::FIXTURE_DIR . '/' . $file;
        if (!is_file($path)) {
            return null;
        }

        $image = $this->imageService->upload(new UploadedFile($path, $file), $creator, ImageType::PluginPhotosPhoto);
        if (!$image instanceof Image) {
            return null;
        }
        $this->imageService->createThumbnails($image, ImageType::PluginPhotosPhoto);

        $meta = $this->exifService->extract($path);

        $photo = new Photo();
        $photo->setImage($image);
        $photo->setCreatedBy((int) $creator->getId());
        $photo->setCreatedAt(new DateTimeImmutable());
        $photo->setMeta($meta);
        $photo->setTakenAt($this->exifService->takenAtOf($meta));

        foreach ($translations as $language => $fields) {
            $translation = new PhotoTranslation();
            $translation->setPhoto($photo);
            $translation->setLanguage($language);
            $translation->setTitle($fields['title']);
            $translation->setDescription($fields['description'] ?? null);
            $photo->addTranslation($translation);
        }

        $manager->persist($photo);
        $manager->flush();

        return $photo;
    }

    /**
     * @return list<array{0: string, 1: array<string, array<string, string>>}>
     */
    private function getData(): array
    {
        return [
            ['harbour-morning.jpg', [
                'en' => ['title' => 'Harbour, just after sunrise', 'description' => 'The first ferry of the day leaving the old pier.'],
                'de' => ['title' => 'Hafen kurz nach Sonnenaufgang', 'description' => 'Die erste Fähre des Tages legt am alten Pier ab.'],
            ]],
            ['winter-forest.jpg', [
                'en' => ['title' => 'Winter forest', 'description' => 'Cold enough that the tripod head stopped moving.'],
            ]],
        ];
    }
}
