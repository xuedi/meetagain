<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Activity;
use App\Entity\Image;
use App\Entity\SupportRequest;
use App\Entity\User;
use App\EntityActionDispatcher;
use App\ExtendedFilesystem;
use App\Repository\ImageRepository;
use App\Repository\IncidentRepository;
use App\Repository\SupportRequestRepository;
use App\Repository\UserRepository;
use App\Service\Security\MeasureLogger;
use App\Service\Security\MeasureSettings;
use App\Service\Support\ThreadService;
use App\Service\System\CleanupService;
use Doctrine\Common\Collections\ArrayCollection;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;

class CleanupServiceTest extends TestCase
{
    private function createService(
        ?ImageRepository $imageRepo = null,
        ?UserRepository $userRepo = null,
        ?EntityManagerInterface $entityManager = null,
        ?EntityActionDispatcher $entityActionDispatcher = null,
        ?LoggerInterface $logger = null,
        ?SupportRequestRepository $supportRequestRepo = null,
        ?ThreadService $threadService = null,
        ?ClockInterface $clock = null,
        ?ExtendedFilesystem $fs = null,
        ?IncidentRepository $incidentRepo = null,
    ): CleanupService {
        return new CleanupService(
            imageRepo: $imageRepo ?? $this->createStub(ImageRepository::class),
            userRepo: $userRepo ?? $this->createStub(UserRepository::class),
            supportRequestRepo: $supportRequestRepo ?? $this->createStub(SupportRequestRepository::class),
            incidentRepo: $incidentRepo ?? $this->createStub(IncidentRepository::class),
            threadService: $threadService ?? $this->createStub(ThreadService::class),
            measureLogger: $this->createStub(MeasureLogger::class),
            measureSettings: $this->createStub(MeasureSettings::class),
            entityManager: $entityManager ?? $this->createStub(EntityManagerInterface::class),
            entityActionDispatcher: $entityActionDispatcher ?? $this->createStub(EntityActionDispatcher::class),
            clock: $clock ?? new MockClock('2026-08-19 12:00:00', 'UTC'),
            logger: $logger ?? $this->createStub(LoggerInterface::class),
            fs: $fs ?? $this->createStub(ExtendedFilesystem::class),
            pendingImportDir: '/app/var/import',
        );
    }

    public function testRemoveExpiredIncidentsDeletesIncidentsEndedMoreThanHalfAYearAgo(): void
    {
        // Arrange
        $incidentRepo = $this->createMock(IncidentRepository::class);
        $incidentRepo
            ->expects($this->once())
            ->method('deleteEndedBefore')
            ->with(new DateTimeImmutable('2026-02-20 12:00:00', new DateTimeZone('UTC')))
            ->willReturn(4);
        $subject = $this->createService(incidentRepo: $incidentRepo);

        // Act
        $deleted = $subject->removeExpiredIncidents();

        // Assert
        static::assertSame(4, $deleted);
    }

    public function testRemoveStaleImportArchivesDeletesUploadsWaitingLongerThanADay(): void
    {
        // Arrange
        $now = new DateTimeImmutable('2026-08-19 12:00:00', new DateTimeZone('UTC'));
        $fs = $this->createMock(ExtendedFilesystem::class);
        $fs->expects($this->once())->method('glob')->with('/app/var/import/*.zip')->willReturn(['/app/var/import/stale.zip', '/app/var/import/fresh.zip']);
        $fs->expects($this->exactly(2))->method('getFileModifiedTime')->willReturnMap([
            ['/app/var/import/stale.zip', $now->modify('-25 hours')->getTimestamp()],
            ['/app/var/import/fresh.zip', $now->modify('-1 hour')->getTimestamp()],
        ]);
        $fs->expects($this->once())->method('deleteFile')->with('/app/var/import/stale.zip')->willReturn(true);

        $subject = $this->createService(fs: $fs);

        // Act
        $count = $subject->removeStaleImportArchives();

        // Assert
        static::assertSame(1, $count);
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


    public function testAutoResolveStaleSupportThreadsResolvesAfterOneHundredEightyDays(): void
    {
        // Arrange
        $stale = new SupportRequest();

        $supportRepoMock = $this->createMock(SupportRequestRepository::class);
        $supportRepoMock
            ->expects($this->once())
            ->method('findStaleUnresolved')
            ->with(new DateTimeImmutable('2026-02-20 12:00:00', new DateTimeZone('UTC')))
            ->willReturn([$stale]);

        $threadService = $this->createMock(ThreadService::class);
        $threadService->expects($this->once())->method('resolve')->with($stale);

        $subject = $this->createService(supportRequestRepo: $supportRepoMock, threadService: $threadService);

        // Act
        $count = $subject->autoResolveStaleSupportThreads();

        // Assert
        static::assertSame(1, $count);
    }

    public function testExpireSupportEmailVerificationsClearsTokensPastTheirExpiry(): void
    {
        // Arrange
        $expired = new SupportRequest();

        $supportRepoMock = $this->createMock(SupportRequestRepository::class);
        $supportRepoMock
            ->expects($this->once())
            ->method('findExpiredEmailVerifications')
            ->with(new DateTimeImmutable('2026-08-19 12:00:00', new DateTimeZone('UTC')))
            ->willReturn([$expired]);

        $threadService = $this->createMock(ThreadService::class);
        $threadService->expects($this->once())->method('clearEmailVerification')->with($expired);

        $subject = $this->createService(supportRequestRepo: $supportRepoMock, threadService: $threadService);

        // Act
        $count = $subject->expireSupportEmailVerifications();

        // Assert
        static::assertSame(1, $count);
    }
}
