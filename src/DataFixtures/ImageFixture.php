<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Image;
use App\Entity\ImageType;
use App\Entity\User;
use App\Service\ImageService;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageFixture extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly UserFixture $userFixture,
    ) {
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        foreach ($this->userFixture->getUsernames() as $name) {
            $path = __DIR__ . "/Avatars/$name.jpg";

            // prepare import user
            $user = $this->getReference('user_' . md5((string)$name), User::class);

            // upload file & thumbnails
            $uploadedImage = new UploadedFile($path, "$name.jpg");
            $image = $this->imageService->upload($uploadedImage, $user, ImageType::ProfilePicture);
            $manager->flush();
            $this->imageService->createThumbnails($image);

            // associate image with user
            $user->setImage($image);
            $manager->persist($user);
            $manager->flush();
        }
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [
            UserFixture::class,
        ];
    }
}
