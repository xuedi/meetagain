<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Activity;
use App\Entity\Image;
use App\Entity\SupportRequest;
use App\Entity\User;
use App\EntityActionDispatcher;
use App\Portability\ExportAction;
use App\Repository\AccessDeniedLogRepository;
use App\Repository\ImageRepository;
use App\Repository\IncidentRepository;
use App\Repository\NotFoundLogRepository;
use App\Repository\RateLimitLogRepository;
use App\Repository\SupportRequestRepository;
use App\Repository\UserRepository;
use App\Service\Security\MeasureLogger;
use App\Service\Security\MeasureSettings;
use App\Service\Support\ThreadService;
use App\Service\System\CleanupService;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Module\Task\Contract\Status;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
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
        ?FilesystemOperator $privateStorage = null,
        ?IncidentRepository $incidentRepo = null,
        ?MeasureLogger $measureLogger = null,
        ?MeasureSettings $measureSettings = null,
        ?NotFoundLogRepository $notFoundLogRepo = null,
        ?AccessDeniedLogRepository $accessDeniedLogRepo = null,
        ?RateLimitLogRepository $rateLimitLogRepo = null,
    ): CleanupService {
        return new CleanupService(
            imageRepo: $imageRepo ?? $this->createStub(ImageRepository::class),
            userRepo: $userRepo ?? $this->createStub(UserRepository::class),
            supportRequestRepo: $supportRequestRepo ?? $this->createStub(SupportRequestRepository::class),
            incidentRepo: $incidentRepo ?? $this->createStub(IncidentRepository::class),
            notFoundLogRepo: $notFoundLogRepo ?? $this->createStub(NotFoundLogRepository::class),
            accessDeniedLogRepo: $accessDeniedLogRepo ?? $this->createStub(AccessDeniedLogRepository::class),
            rateLimitLogRepo: $rateLimitLogRepo ?? $this->createStub(RateLimitLogRepository::class),
            threadService: $threadService ?? $this->createStub(ThreadService::class),
            measureLogger: $measureLogger ?? $this->createStub(MeasureLogger::class),
            measureSettings: $measureSettings ?? $this->createStub(MeasureSettings::class),
            entityManager: $entityManager ?? $this->createStub(EntityManagerInterface::class),
            entityActionDispatcher: $entityActionDispatcher ?? $this->createStub(EntityActionDispatcher::class),
            clock: $clock ?? new MockClock('2026-08-19 12:00:00', 'UTC'),
            logger: $logger ?? $this->createStub(LoggerInterface::class),
            privateStorage: $privateStorage ?? new Filesystem(new InMemoryFilesystemAdapter()),
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

    public function testRemoveExpiredRequestLogsCutsEveryLogAtNinetyDays(): void
    {
        // Arrange
        $cutoff = new DateTimeImmutable('2026-05-21 12:00:00', new DateTimeZone('UTC'));
        $notFoundLogRepo = $this->createMock(NotFoundLogRepository::class);
        $notFoundLogRepo->expects($this->once())->method('deleteUnlinkedBefore')->with($cutoff)->willReturn(7);
        $accessDeniedLogRepo = $this->createMock(AccessDeniedLogRepository::class);
        $accessDeniedLogRepo->expects($this->once())->method('deleteUnlinkedBefore')->with($cutoff)->willReturn(2);
        $rateLimitLogRepo = $this->createMock(RateLimitLogRepository::class);
        $rateLimitLogRepo->expects($this->once())->method('deleteBefore')->with($cutoff)->willReturn(1);
        $subject = $this->createService(notFoundLogRepo: $notFoundLogRepo, accessDeniedLogRepo: $accessDeniedLogRepo, rateLimitLogRepo: $rateLimitLogRepo);

        // Act
        $deleted = $subject->removeExpiredRequestLogs();

        // Assert
        static::assertSame(['not_found' => 7, 'access_denied' => 2, 'rate_limit' => 1], $deleted);
    }

    public function testRemoveStaleImportArchivesDeletesUploadsWaitingLongerThanADay(): void
    {
        // Arrange
        $now = new DateTimeImmutable('2026-08-19 12:00:00', new DateTimeZone('UTC'));
        $storage = new Filesystem(new InMemoryFilesystemAdapter());
        $storage->write('import/archive/stale.zip', 'zip', ['timestamp' => $now->modify('-25 hours')->getTimestamp()]);
        $storage->write('import/archive/fresh.zip', 'zip', ['timestamp' => $now->modify('-1 hour')->getTimestamp()]);
        $storage->write('invoices/2026/INV-1.pdf', 'pdf', ['timestamp' => $now->modify('-1 year')->getTimestamp()]);

        $subject = $this->createService(privateStorage: $storage);

        // Act
        $count = $subject->removeStaleImportArchives();

        // Assert
        static::assertSame(1, $count);
        static::assertFalse($storage->fileExists('import/archive/stale.zip'));
        static::assertTrue($storage->fileExists('import/archive/fresh.zip'));
        static::assertTrue($storage->fileExists('invoices/2026/INV-1.pdf'));
    }

    public function testRemoveStaleExportArchiveDeletesAnArchiveOlderThanADay(): void
    {
        // Arrange
        $now = new DateTimeImmutable('2026-08-19 12:00:00', new DateTimeZone('UTC'));
        $storage = new Filesystem(new InMemoryFilesystemAdapter());
        $storage->write(ExportAction::ARCHIVE, 'zip', ['timestamp' => $now->modify('-25 hours')->getTimestamp()]);
        $subject = $this->createService(privateStorage: $storage);

        // Act
        $count = $subject->removeStaleExportArchive();

        // Assert
        static::assertSame(1, $count);
        static::assertFalse($storage->fileExists(ExportAction::ARCHIVE));
    }

    public function testRemoveStaleExportArchiveKeepsAFreshArchive(): void
    {
        // Arrange
        $now = new DateTimeImmutable('2026-08-19 12:00:00', new DateTimeZone('UTC'));
        $storage = new Filesystem(new InMemoryFilesystemAdapter());
        $storage->write(ExportAction::ARCHIVE, 'zip', ['timestamp' => $now->modify('-1 hour')->getTimestamp()]);
        $subject = $this->createService(privateStorage: $storage);

        // Act
        $count = $subject->removeStaleExportArchive();

        // Assert
        static::assertSame(0, $count);
        static::assertTrue($storage->fileExists(ExportAction::ARCHIVE));
    }

    public function testRemoveStaleExportArchiveWithoutAnArchiveDoesNothing(): void
    {
        // Arrange
        $subject = $this->createService();

        // Act
        $count = $subject->removeStaleExportArchive();

        // Assert
        static::assertSame(0, $count);
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

    public function testRemoveExpiredSecurityMeasureLogsPurgesBeyondTheConfiguredRetention(): void
    {
        // Arrange
        $measureSettings = $this->createStub(MeasureSettings::class);
        $measureSettings->method('logRetentionDays')->willReturn(14);
        $measureLogger = $this->createMock(MeasureLogger::class);
        $measureLogger->expects($this->once())->method('purgeOlderThan')->with(14)->willReturn(7);
        $subject = $this->createService(measureLogger: $measureLogger, measureSettings: $measureSettings);

        // Act
        $count = $subject->removeExpiredSecurityMeasureLogs();

        // Assert
        static::assertSame(7, $count);
    }

    public function testRunCronTaskReportsTheCountOfEveryCleanupStep(): void
    {
        // Arrange
        $imageRepo = $this->createStub(ImageRepository::class);
        $imageRepo->method('getOldImageUpdates')->willReturn([$this->createStub(Image::class), $this->createStub(Image::class)]);
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(42);
        $user->method('getActivities')->willReturn(new ArrayCollection());
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('getOldRegistrations')->willReturn([$user]);
        $supportRepo = $this->createStub(SupportRequestRepository::class);
        $supportRepo->method('findStaleUnresolved')->willReturn([new SupportRequest(), new SupportRequest(), new SupportRequest()]);
        $supportRepo->method('findExpiredEmailVerifications')->willReturn([]);
        $measureLogger = $this->createStub(MeasureLogger::class);
        $measureLogger->method('purgeOlderThan')->willReturn(5);
        $incidentRepo = $this->createStub(IncidentRepository::class);
        $incidentRepo->method('deleteEndedBefore')->willReturn(6);
        $notFoundLogRepo = $this->createStub(NotFoundLogRepository::class);
        $notFoundLogRepo->method('deleteUnlinkedBefore')->willReturn(7);
        $subject = $this->createService(
            imageRepo: $imageRepo,
            userRepo: $userRepo,
            supportRequestRepo: $supportRepo,
            incidentRepo: $incidentRepo,
            measureLogger: $measureLogger,
            notFoundLogRepo: $notFoundLogRepo,
        );

        // Act
        $result = $subject->run();

        // Assert
        static::assertSame(Status::ok, $result->status);
        static::assertSame(
            'image_cache: 2, registrations: 1, support_threads_auto_resolved: 3, support_email_verifications_expired: 0, import_archives: 0, export_archive: 0, security_measure_logs: 5, security_incidents: 6, not_found_logs: 7, access_denied_logs: 0, rate_limit_logs: 0',
            $result->message,
        );
    }

    public function testRunCronTaskReportsAnExceptionWhenACleanupStepFails(): void
    {
        // Arrange
        $imageRepo = $this->createStub(ImageRepository::class);
        $imageRepo->method('getOldImageUpdates')->willThrowException(new RuntimeException('database gone'));
        $subject = $this->createService(imageRepo: $imageRepo);

        // Act
        $result = $subject->run();

        // Assert
        static::assertSame(Status::exception, $result->status);
        static::assertSame('database gone', $result->message);
    }
}
