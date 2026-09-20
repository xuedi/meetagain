<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Activity\ActivityService;
use App\Activity\Messages\UpdatedProfilePicture;
use App\Entity\Image;
use App\Entity\User;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Enum\ImageType;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class AvatarService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ImageService $imageService,
        private ImageLocationService $imageLocationService,
        private EntityActionDispatcher $entityActionDispatcher,
        private ActivityService $activityService,
    ) {}

    public function replace(User $user, UploadedFile $file): ?Image
    {
        $previous = $user->getImage()?->getId() ?? 0;

        $image = $this->imageService->upload($file, $user, ImageType::ProfilePicture);
        if (!$image instanceof Image) {
            return null;
        }

        $image->setUploader($user);
        $image->setUpdatedAt(new DateTimeImmutable());
        $this->em->persist($image);
        $this->imageService->createThumbnails($image, ImageType::ProfilePicture);
        $this->em->flush();
        $this->entityActionDispatcher->dispatch(EntityAction::CreateImage, $image->getId());

        $user->setImage($image);
        $this->em->persist($user);
        $this->em->flush();

        $current = (int) $image->getId();
        if ($previous > 0) {
            $this->imageLocationService->removeLocation($previous, ImageType::ProfilePicture, (int) $user->getId());
        }
        $this->imageLocationService->addLocation($current, ImageType::ProfilePicture, (int) $user->getId());

        $this->activityService->log(UpdatedProfilePicture::TYPE, $user, ['old' => $previous, 'new' => $current]);

        return $image;
    }
}
