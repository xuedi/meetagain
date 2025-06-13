<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\CmsBlock;
use App\Entity\Event;
use App\Entity\ImageType;
use App\Entity\User;
use App\Service\ImageService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageFixture extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly CmsBlockFixture $cmsBlockFixture,
        private readonly ImageService $imageService,
        private readonly UserFixture $userFixture,
        private readonly EventFixture $eventFixture,
    )
    {
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating images .';
        $importUser = $this->getReference('user_' . md5('import'), User::class);
        $adminUser = $this->getReference('user_' . md5('admin'), User::class);

        // add photos for cmdBlocks
        foreach ($this->cmsBlockFixture->getBlockReferenceForImages() as $blockId) {
            $imageFile = __DIR__ . "/CmsBlock/$blockId";
            $reference = 'cmsBlock_' . md5($blockId);
            $cmsBlock = $this->getReference($reference, CmsBlock::class);

            // upload file & thumbnails
            $uploadedImage = new UploadedFile($imageFile, "$blockId.jpg");
            $image = $this->imageService->upload($uploadedImage, $importUser, ImageType::CmsBlock);
            $manager->flush();
            $this->imageService->createThumbnails($image);

            // associate image with a user
            $cmsBlock->setImage($image);
            $manager->persist($cmsBlock);
            $manager->flush();
        }

        // add user profile photos
        foreach ($this->userFixture->getUsernames() as $name) {
            $imageFile = __DIR__ . "/Avatars/$name.jpg";
            $reference = 'user_' . md5((string)$name);
            $user = $this->getReference($reference, User::class);

            // upload file & thumbnails
            $uploadedImage = new UploadedFile($imageFile, "$name.jpg");
            $image = $this->imageService->upload($uploadedImage, $user, ImageType::ProfilePicture);
            $manager->flush();
            $this->imageService->createThumbnails($image);

            // associate image with a user
            $user->setImage($image);
            $manager->persist($user);
            $manager->flush();
        }
        echo '.';

        // add event preview photos
        $eventList = $this->eventFixture->getEventNames();
        foreach ($eventList as $name) {
            $reference = 'event_' . md5((string)$name);
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
            $event = $this->getReference('event_' . md5((string)$name), Event::class);
            $randomPhotos = array_rand(array_flip($eventPhotos), random_int(6, 15));
            foreach ($randomPhotos as $imageFile) {
                $uploadedImage = new UploadedFile($imageFile, "image.jpg");
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
        if (!is_dir($path)) {
            return [];
        }

        $files = glob($path . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
        return $files ?: [];
    }
}
