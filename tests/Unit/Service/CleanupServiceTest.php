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
    private MockObject|EntityManagerInterface $entityManagerMock;
    private MockObject|ImageRepository $imageRepoMock;
    private MockObject|UserRepository $userRepoMock;
    private CleanupService $subject;

    protected function setUp(): void
    {
        $this->imageRepoMock = $this->createMock(ImageRepository::class);
        $this->userRepoMock = $this->createMock(UserRepository::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);

        $this->subject = new CleanupService(
            imageRepo: $this->imageRepoMock,
            userRepo: $this->userRepoMock,
            entityManager: $this->entityManagerMock
        );
    }

    public function testRemoveImageCache(): void
    {
        $imageMockA = $this->createMock(Image::class);
        $imageMockA->expects($this->once())->method('setUpdatedAt');

        $imageMockB = $this->createMock(Image::class);
        $imageMockB->expects($this->once())->method('setUpdatedAt');

        $this->imageRepoMock
            ->method('getOldImageUpdates')
            ->willReturn([$imageMockA, $imageMockB]);

        $this->entityManagerMock
            ->expects($this->exactly(2))
            ->method('persist');

        $this->entityManagerMock
            ->expects($this->once())
            ->method('flush');

        $this->subject->removeImageCache();
    }

    public function testRemoveGhostedRegistrations(): void
    {
        $activityMock = $this->createMock(Activity::class);

        $userMock = $this->createMock(User::class);
        $userMock
            ->expects($this->once())
            ->method('getActivities')
            ->willReturn(new ArrayCollection([$activityMock]));

        $this->userRepoMock
            ->expects($this->once())
            ->method('getOldRegistrations')
            ->with(10)
            ->willReturn(new ArrayCollection([$userMock]));

        $this->entityManagerMock
            ->expects($this->exactly(2))
            ->method('remove');

        $this->entityManagerMock
            ->expects($this->once())
            ->method('flush');

        $this->subject->removeGhostedRegistrations();
    }
}
