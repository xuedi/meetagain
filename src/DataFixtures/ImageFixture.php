<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\CmsBlock;
use App\Entity\Event;
use App\Entity\ImageType;
use App\Entity\User;
use App\ExtendedFilesystem;
use App\Service\ImageService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageFixture extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly EventFixture $eventFixture,
        private readonly ExtendedFilesystem $filesystem,
    ) {
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating images .';
        $importUser = $this->getReference('user_' . md5('import'), User::class);
        $adminUser = $this->getReference('user_' . md5('admin'), User::class);

        // add event preview photos
        $eventList = $this->eventFixture->getEventNames();
        foreach ($eventList as $name) {
            $reference = 'event_' . md5((string) $name);
            $imageFile = __DIR__ . "/Events/$reference.jpg";
            $event = $this->getReference($reference, Event::class);

            // upload file & thumbnails
            $uploadedImage = new UploadedFile($imageFile, "$reference.jpg");
            $image = $this->imageService->upload($uploadedImage, $importUser, ImageType::EventTeaser);
            $manager->flush();
            $this->imageService->createThumbnails($image);

            // associate image with the event
            $event->setPreviewImage($image);
            $manager->persist($event);
            $manager->flush();
        }
        echo '.';

        // add photos to 2 random events
        $eventPhotos = $this->getEventPhotos();
        $randomEvents = array_rand(array_flip($eventList), 4);
        foreach ($randomEvents as $name) {
            $event = $this->getReference('event_' . md5((string) $name), Event::class);
            $randomPhotos = array_rand(array_flip($eventPhotos), random_int(6, 15));
            foreach ($randomPhotos as $imageFile) {
                $uploadedImage = new UploadedFile($imageFile, 'image.jpg');
                $image = $this->imageService->upload($uploadedImage, $adminUser, ImageType::EventUpload);
                $manager->flush();
                $this->imageService->createThumbnails($image);

                $event->addImage($image);
            }
            $manager->persist($event);
            $manager->flush();
        }
        echo ' OK' . PHP_EOL;
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [
            UserFixture::class,
            EventFixture::class,
            CmsBlockFixture::class,
        ];
    }

    private function getEventPhotos(): array
    {
        $path = __DIR__ . '/EventPhotos';
        if (!$this->filesystem->isDirectory($path)) {
            return [];
        }

        return $this->filesystem->glob($path . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
    }
}
