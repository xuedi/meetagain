<?php declare(strict_types=1);

namespace Tests\Unit\Moderation;

use App\Activity\ActivityService;
use App\Activity\Messages\ReportedSubject;
use App\Emails\Types\ModerationWarningEmail;
use App\Entity\ModerationReport;
use App\Enum\ModerationReportReason;
use App\Enum\ModerationReportStatus;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Moderation\ReportService;
use App\Moderation\SubjectProviderInterface;
use App\Moderation\SubjectSnapshot;
use App\Repository\ModerationReportRepository;
use App\Service\Member\UserService;
use App\Service\Security\ContentSanitizer;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Tests\Unit\Stubs\UserStub;

class ReportServiceTest extends TestCase
{
    public function testCreatePersistsSanitizedReportAndLogsActivity(): void
    {
        // Arrange
        $author = $this->makeUser(2);
        $reporter = $this->makeUser(1);
        $em = $this->createMock(EntityManagerInterface::class);
        $persisted = null;
        $em
            ->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (ModerationReport $report) use (&$persisted): void {
                $persisted = $report;
            });
        $activity = $this->createMock(ActivityService::class);
        $activity->expects(self::once())->method('log')->with(ReportedSubject::TYPE, $reporter, [
            'subject_type' => 'comment',
            'subject_id' => 11,
            'reason' => 'spam',
            'remarks' => 'Spam link',
        ]);
        $service = $this->makeService(em: $em, activity: $activity);

        // Act
        $service->create('comment', 11, new SubjectSnapshot('event #7', 'Buy now', $author), $reporter, ModerationReportReason::Spam, ' <b>Spam</b> link ');

        // Assert
        static::assertInstanceOf(ModerationReport::class, $persisted);
        static::assertSame('event #7', $persisted->getSubjectLabel());
        static::assertSame('Buy now', $persisted->getExcerpt());
        static::assertSame($author, $persisted->getAuthor());
        static::assertSame('Spam link', $persisted->getRemarks());
        static::assertTrue($persisted->isOpen());
    }

    public function testCreateSkipsADuplicateOpenReportFromTheSameMember(): void
    {
        // Arrange
        $repository = $this->createStub(ModerationReportRepository::class);
        $repository->method('findOpenByReporter')->willReturn($this->makeReport());
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $service = $this->makeService(repository: $repository, em: $em);

        // Act
        $service->create('user', 2, new SubjectSnapshot('Orlando', null, $this->makeUser(2)), $this->makeUser(1), ModerationReportReason::Spam, null);
    }

    public function testDismissResolvesEveryOpenReportOnTheSubject(): void
    {
        // Arrange
        $first = $this->makeReport();
        $second = $this->makeReport();
        $repository = $this->createStub(ModerationReportRepository::class);
        $repository->method('findOpenForSubject')->willReturn([$first, $second]);
        $admin = $this->makeUser(9);
        $service = $this->makeService(repository: $repository);

        // Act
        $service->dismiss($first, $admin);

        // Assert
        foreach ([$first, $second] as $report) {
            static::assertSame(ModerationReportStatus::Dismissed, $report->getStatus());
            static::assertSame($admin, $report->getResolvedBy());
        }
    }

    public function testRemoveSubjectRunsTheProviderAndActionsTheReports(): void
    {
        // Arrange
        $report = $this->makeReport();
        $repository = $this->createStub(ModerationReportRepository::class);
        $repository->method('findOpenForSubject')->willReturn([$report]);
        $provider = $this->createMock(SubjectProviderInterface::class);
        $provider->method('getTypeKey')->willReturn('comment');
        $provider->method('getRemoveLabelKey')->willReturn('admin_support_moderation.button_remove_comment');
        $provider->expects(self::once())->method('remove')->with(11);
        $service = $this->makeService(repository: $repository, providers: [$provider]);

        // Act
        $service->removeSubject($report, $this->makeUser(9));

        // Assert
        static::assertSame(ModerationReportStatus::Actioned, $report->getStatus());
    }

    public function testRemoveSubjectDoesNothingWhenTheKindOffersNoRemoval(): void
    {
        // Arrange
        $report = $this->makeReport();
        $provider = $this->createMock(SubjectProviderInterface::class);
        $provider->method('getTypeKey')->willReturn('comment');
        $provider->method('getRemoveLabelKey')->willReturn(null);
        $provider->expects(self::never())->method('remove');
        $service = $this->makeService(providers: [$provider]);

        // Act
        $service->removeSubject($report, $this->makeUser(9));

        // Assert
        static::assertTrue($report->isOpen());
    }

    public function testBlockAuthorBlocksAnActiveAuthorAndActionsTheReports(): void
    {
        // Arrange
        $author = $this->makeUser(2);
        $report = $this->makeReport()->setAuthor($author);
        $repository = $this->createStub(ModerationReportRepository::class);
        $repository->method('findOpenForSubject')->willReturn([$report]);
        $admin = $this->makeUser(9);
        $userService = $this->createMock(UserService::class);
        $userService->expects(self::once())->method('transitionStatus')->with($admin, $author, UserStatus::Blocked);
        $service = $this->makeService(repository: $repository, userService: $userService);

        // Act
        $service->blockAuthor($report, $admin);

        // Assert
        static::assertSame(ModerationReportStatus::Actioned, $report->getStatus());
    }

    #[DataProvider('provideUnblockableAuthors')]
    public function testBlockAuthorLeavesAnUnblockableAuthorAlone(?UserStub $author): void
    {
        // Arrange
        $report = $this->makeReport()->setAuthor($author);
        $userService = $this->createMock(UserService::class);
        $userService->expects(self::never())->method('transitionStatus');
        $service = $this->makeService(userService: $userService);

        // Act
        $service->blockAuthor($report, new UserStub()->setId(9));

        // Assert
        static::assertTrue($report->isOpen());
    }

    public static function provideUnblockableAuthors(): iterable
    {
        yield 'deleted account' => [null];
        yield 'already blocked' => [new UserStub()
            ->setId(2)
            ->setStatus(UserStatus::Blocked)];
        yield 'the acting admin' => [new UserStub()
            ->setId(9)
            ->setStatus(UserStatus::Active)];
        yield 'the system user' => [new UserStub()
            ->setId(2)
            ->setStatus(UserStatus::Active)
            ->setRole(UserRole::System)];
    }

    public function testEveryReportOnTheSubjectKeepsTheSanitizedNote(): void
    {
        // Arrange
        $first = $this->makeReport();
        $second = $this->makeReport();
        $repository = $this->createStub(ModerationReportRepository::class);
        $repository->method('findOpenForSubject')->willReturn([$first, $second]);
        $service = $this->makeService(repository: $repository);

        // Act
        $service->dismiss($first, $this->makeUser(9), ' <i>Not</i> a rule break ');

        // Assert
        static::assertSame('Not a rule break', $first->getResolutionNote());
        static::assertSame('Not a rule break', $second->getResolutionNote());
    }

    public function testSuspendAuthorBlocksUntilTheDayCountFromNow(): void
    {
        // Arrange
        $author = $this->makeUser(2);
        $report = $this->makeReport()->setAuthor($author);
        $repository = $this->createStub(ModerationReportRepository::class);
        $repository->method('findOpenForSubject')->willReturn([$report]);
        $admin = $this->makeUser(9);
        $userService = $this->createMock(UserService::class);
        $userService
            ->expects(self::once())
            ->method('transitionStatus')
            ->with(
                $admin,
                $author,
                UserStatus::Blocked,
                self::callback(static fn(DateTimeImmutable $until): bool => $until->format('Y-m-d H:i') === '2026-10-16 12:00'),
            );
        $service = $this->makeService(repository: $repository, userService: $userService);

        // Act
        $suspended = $service->suspendAuthor($report, $admin, 7, 'First offence');

        // Assert
        static::assertTrue($suspended);
        static::assertSame(ModerationReportStatus::Actioned, $report->getStatus());
        static::assertSame('First offence', $report->getResolutionNote());
    }

    #[DataProvider('provideInvalidSuspensionLengths')]
    public function testSuspendAuthorRefusesALengthOutsideTheRange(int $days): void
    {
        // Arrange
        $report = $this->makeReport()->setAuthor($this->makeUser(2));
        $userService = $this->createMock(UserService::class);
        $userService->expects(self::never())->method('transitionStatus');
        $service = $this->makeService(userService: $userService);

        // Act
        $suspended = $service->suspendAuthor($report, $this->makeUser(9), $days);

        // Assert
        static::assertFalse($suspended);
        static::assertTrue($report->isOpen());
    }

    public static function provideInvalidSuspensionLengths(): iterable
    {
        yield 'zero days' => [0];
        yield 'negative' => [-3];
        yield 'over a year' => [ReportService::MAX_SUSPENSION_DAYS + 1];
    }

    public function testWarnAuthorMailsTheNoteAndActionsTheReports(): void
    {
        // Arrange
        $report = $this->makeReport()->setAuthor($this->makeUser(2));
        $repository = $this->createStub(ModerationReportRepository::class);
        $repository->method('findOpenForSubject')->willReturn([$report]);
        $email = $this->createMock(ModerationWarningEmail::class);
        $email->expects(self::once())->method('send')->with(['report' => $report, 'note' => 'Stop the ads.']);
        $service = $this->makeService(repository: $repository, warningEmail: $email);

        // Act
        $warned = $service->warnAuthor($report, $this->makeUser(9), 'Stop the ads.');

        // Assert
        static::assertTrue($warned);
        static::assertSame(ModerationReportStatus::Actioned, $report->getStatus());
        static::assertSame('Stop the ads.', $report->getResolutionNote());
    }

    public function testWarnAuthorNeedsANote(): void
    {
        // Arrange
        $report = $this->makeReport()->setAuthor($this->makeUser(2));
        $email = $this->createMock(ModerationWarningEmail::class);
        $email->expects(self::never())->method('send');
        $service = $this->makeService(warningEmail: $email);

        // Act
        $warned = $service->warnAuthor($report, $this->makeUser(9), '   ');

        // Assert
        static::assertFalse($warned);
        static::assertTrue($report->isOpen());
    }

    public function testABlockedAuthorCanStillBeWarnedButNotBlockedAgain(): void
    {
        // Arrange
        $author = $this->makeUser(2)->setStatus(UserStatus::Blocked);
        $report = $this->makeReport()->setAuthor($author);
        $admin = $this->makeUser(9);
        $service = $this->makeService();

        // Act
        $canWarn = $service->canWarnAuthor($report, $admin);
        $canBlock = $service->canBlockAuthor($report, $admin);

        // Assert
        static::assertTrue($canWarn);
        static::assertFalse($canBlock);
    }

    public function testAuthorHistoryFillsMissingStatusesWithZero(): void
    {
        // Arrange
        $author = $this->makeUser(2);
        $repository = $this->createStub(ModerationReportRepository::class);
        $repository->method('countByAuthorPerStatus')->willReturn(['dismissed' => 2, 'actioned' => 1]);
        $repository->method('findLastActionedAtForAuthor')->willReturn(new DateTimeImmutable('2026-09-01'));
        $service = $this->makeService(repository: $repository);

        // Act
        $history = $service->authorHistory($author);

        // Assert
        static::assertSame(0, $history->open);
        static::assertSame(2, $history->dismissed);
        static::assertSame(1, $history->actioned);
        static::assertSame(3, $history->getTotal());
        static::assertSame('2026-09-01', $history->lastActionedAt?->format('Y-m-d'));
    }

    /**
     * @param list<SubjectProviderInterface> $providers
     */
    private function makeService(
        ?ModerationReportRepository $repository = null,
        ?EntityManagerInterface $em = null,
        ?ActivityService $activity = null,
        ?UserService $userService = null,
        array $providers = [],
        ?ModerationWarningEmail $warningEmail = null,
    ): ReportService {
        $config = new HtmlSanitizerConfig()->allowSafeElements();

        return new ReportService(
            $providers,
            $repository ?? $this->createStub(ModerationReportRepository::class),
            $em ?? $this->createStub(EntityManagerInterface::class),
            new ContentSanitizer(new HtmlSanitizer($config), new HtmlSanitizer($config)),
            $activity ?? $this->createStub(ActivityService::class),
            $userService ?? $this->createStub(UserService::class),
            $warningEmail ?? $this->createStub(ModerationWarningEmail::class),
            new MockClock('2026-10-09 12:00:00'),
        );
    }

    private function makeReport(): ModerationReport
    {
        return new ModerationReport('comment', 11, ModerationReportReason::Spam, new DateTimeImmutable('2026-10-01 12:00'));
    }

    private function makeUser(int $id): UserStub
    {
        return new UserStub()
            ->setId($id)
            ->setStatus(UserStatus::Active);
    }
}
