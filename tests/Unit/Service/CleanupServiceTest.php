<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Activity;
use App\Entity\Image;
use App\Entity\User;
use App\Repository\ImageRepository;
use App\Repository\UserRepository;
use App\Service\CleanupService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CleanupServiceTest extends TestCase
{
    private function createService(
        ?ImageRepository $imageRepo = null,
        ?UserRepository $userRepo = null,
        ?EntityManagerInterface $entityManager = null,
    ): CleanupService {
        return new CleanupService(
            imageRepo: $imageRepo ?? $this->createStub(ImageRepository::class),
            userRepo: $userRepo ?? $this->createStub(UserRepository::class),
            entityManager: $entityManager ?? $this->createStub(EntityManagerInterface::class),
        );
    }

    public function testRemoveImageCacheUpdatesOldImagesAndPersists(): void
    {
        // Arrange: mock images that need cache refresh
        $imageMockA = $this->createMock(Image::class);
        $imageMockA->expects($this->once())->method('setUpdatedAt');

        $imageMockB = $this->createMock(Image::class);
        $imageMockB->expects($this->once())->method('setUpdatedAt');

        // Arrange: mock repository to return images needing update
        $imageRepoMock = $this->createMock(ImageRepository::class);
        $imageRepoMock
            ->expects($this->once())
            ->method('getOldImageUpdates')
            ->willReturn([$imageMockA, $imageMockB]);

        // Arrange: mock entity manager to verify persist and flush
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock->expects($this->exactly(2))->method('persist');
        $entityManagerMock->expects($this->once())->method('flush');

        $subject = $this->createService(
            imageRepo: $imageRepoMock,
            entityManager: $entityManagerMock,
        );

        // Act: remove image cache
        $subject->removeImageCache();
    }

    public function testRemoveGhostedRegistrationsDeletesUsersAndActivities(): void
    {
        // Arrange: stub activity for the user
        $activityStub = $this->createStub(Activity::class);

        // Arrange: mock user to return activities collection
        $userMock = $this->createMock(User::class);
        $userMock
            ->expects($this->once())
            ->method('getActivities')
            ->willReturn(new ArrayCollection([$activityStub]));

        // Arrange: mock user repository to return old registrations
        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock
            ->expects($this->once())
            ->method('getOldRegistrations')
            ->with(10)
            ->willReturn(new ArrayCollection([$userMock]));

        // Arrange: mock entity manager to verify removes and flush
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock->expects($this->exactly(2))->method('remove');
        $entityManagerMock->expects($this->once())->method('flush');

        $subject = $this->createService(
            userRepo: $userRepoMock,
            entityManager: $entityManagerMock,
        );

        // Act: remove ghosted registrations
        $subject->removeGhostedRegistrations();
    }
}
