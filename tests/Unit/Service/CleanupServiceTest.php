<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Activity;
use App\Entity\Image;
use App\Entity\User;
use App\EntityActionDispatcher;
use App\Repository\ImageRepository;
use App\Repository\UserRepository;
use App\Service\System\CleanupService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CleanupServiceTest extends TestCase
{
    private function createService(
        ?ImageRepository $imageRepo = null,
        ?UserRepository $userRepo = null,
        ?EntityManagerInterface $entityManager = null,
        ?EntityActionDispatcher $entityActionDispatcher = null,
        ?LoggerInterface $logger = null,
    ): CleanupService {
        return new CleanupService(
            imageRepo: $imageRepo ?? $this->createStub(ImageRepository::class),
            userRepo: $userRepo ?? $this->createStub(UserRepository::class),
            entityManager: $entityManager ?? $this->createStub(EntityManagerInterface::class),
            entityActionDispatcher: $entityActionDispatcher ?? $this->createStub(EntityActionDispatcher::class),
            logger: $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    public function testRemoveImageCacheUpdatesOldImagesAndPersists(): void
    {
        // Arrange
        $imageMockA = $this->createMock(Image::class);
        $imageMockA->expects($this->once())->method('setUpdatedAt');

        $imageMockB = $this->createMock(Image::class);
        $imageMockB->expects($this->once())->method('setUpdatedAt');

        // Arrange
        $imageRepoMock = $this->createMock(ImageRepository::class);
        $imageRepoMock->expects($this->once())->method('getOldImageUpdates')->willReturn([$imageMockA, $imageMockB]);

        // Arrange
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock->expects($this->exactly(2))->method('persist');
        $entityManagerMock->expects($this->once())->method('flush');

        $subject = $this->createService(imageRepo: $imageRepoMock, entityManager: $entityManagerMock);

        // Act
        $subject->removeImageCache();
    }

    public function testRemoveGhostedRegistrationsDeletesUsersAndActivities(): void
    {
        // Arrange
        $activityStub = $this->createStub(Activity::class);

        // Arrange
        $userMock = $this->createMock(User::class);
        $userMock->method('getId')->willReturn(42);
        $userMock
            ->expects($this->once())
            ->method('getActivities')
            ->willReturn(new ArrayCollection([$activityStub]));

        // Arrange
        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock
            ->expects($this->once())
            ->method('getOldRegistrations')
            ->with(10)
            ->willReturn(new ArrayCollection([$userMock]));

        // Arrange
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock->expects($this->exactly(2))->method('remove');
        $entityManagerMock->expects($this->once())->method('flush');

        $subject = $this->createService(userRepo: $userRepoMock, entityManager: $entityManagerMock);

        // Act
        $subject->removeGhostedRegistrations();
    }
}
