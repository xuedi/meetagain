<?php declare(strict_types=1);

namespace App\Moderation;

use App\Activity\ActivityService;
use App\Activity\Messages\ReportedSubject;
use App\Emails\Types\ModerationWarningEmail;
use App\Entity\ModerationReport;
use App\Entity\User;
use App\Enum\ModerationReportReason;
use App\Enum\ModerationReportStatus;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Repository\ModerationReportRepository;
use App\Service\Member\UserService;
use App\Service\Security\ContentSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class ReportService
{
    public const int MAX_SUSPENSION_DAYS = 365;

    /**
     * @param iterable<SubjectProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(SubjectProviderInterface::class)]
        private iterable $providers,
        private ModerationReportRepository $repository,
        private EntityManagerInterface $em,
        private ContentSanitizer $sanitizer,
        private ActivityService $activityService,
        private UserService $userService,
        private ModerationWarningEmail $warningEmail,
        private ClockInterface $clock,
    ) {}

    public function describe(string $subjectType, int $subjectId, User $reporter): ?SubjectSnapshot
    {
        return $this->providerFor($subjectType)?->describe($subjectId, $reporter);
    }

    public function create(
        string $subjectType,
        int $subjectId,
        SubjectSnapshot $snapshot,
        User $reporter,
        ModerationReportReason $reason,
        ?string $remarks,
    ): void {
        if ($this->repository->findOpenByReporter($reporter, $subjectType, $subjectId) !== null) {
            return;
        }

        $cleanRemarks = $this->sanitizer->toPlainText(trim((string) $remarks));
        $report = new ModerationReport($subjectType, $subjectId, $reason, $this->clock->now())
            ->setSubjectLabel($snapshot->label)
            ->setExcerpt($snapshot->excerpt)
            ->setAuthor($snapshot->author)
            ->setReporter($reporter)
            ->setRemarks($cleanRemarks === '' ? null : $cleanRemarks);

        $this->em->persist($report);
        $this->em->flush();

        $meta = ['subject_type' => $subjectType, 'subject_id' => $subjectId, 'reason' => $reason->value];
        if ($cleanRemarks !== '') {
            $meta['remarks'] = $cleanRemarks;
        }
        $this->activityService->log(ReportedSubject::TYPE, $reporter, $meta);
    }

    public function find(int $id): ?ModerationReport
    {
        return $this->repository->find($id);
    }

    /** @return list<ModerationReport> */
    public function findAll(): array
    {
        return $this->repository->findAllNewestFirst();
    }

    public function getAdminPath(ModerationReport $report): ?string
    {
        return $this->providerFor($report->getSubjectType())?->getAdminPath($report->getSubjectId());
    }

    public function getRemoveLabelKey(ModerationReport $report): ?string
    {
        return $this->providerFor($report->getSubjectType())?->getRemoveLabelKey();
    }

    public function canBlockAuthor(ModerationReport $report, User $admin): bool
    {
        return $this->canWarnAuthor($report, $admin) && $report->getAuthor()?->getStatus() === UserStatus::Active;
    }

    public function canWarnAuthor(ModerationReport $report, User $admin): bool
    {
        $author = $report->getAuthor();

        return $author !== null && $author->getId() !== $admin->getId() && $author->getRole() !== UserRole::System;
    }

    public function authorHistory(User $author): AuthorHistory
    {
        $counts = $this->repository->countByAuthorPerStatus($author);

        return new AuthorHistory(
            open: $counts[ModerationReportStatus::Open->value] ?? 0,
            dismissed: $counts[ModerationReportStatus::Dismissed->value] ?? 0,
            actioned: $counts[ModerationReportStatus::Actioned->value] ?? 0,
            lastActionedAt: $this->repository->findLastActionedAtForAuthor($author),
        );
    }

    public function dismiss(ModerationReport $report, User $admin, ?string $note = null): void
    {
        $this->resolveSubject($report, ModerationReportStatus::Dismissed, $admin, $note);
    }

    public function removeSubject(ModerationReport $report, User $admin, ?string $note = null): bool
    {
        $provider = $this->providerFor($report->getSubjectType());
        if ($provider?->getRemoveLabelKey() === null) {
            return false;
        }

        $provider->remove($report->getSubjectId());
        $this->resolveSubject($report, ModerationReportStatus::Actioned, $admin, $note);

        return true;
    }

    public function blockAuthor(ModerationReport $report, User $admin, ?string $note = null): bool
    {
        $author = $report->getAuthor();
        if ($author === null || !$this->canBlockAuthor($report, $admin)) {
            return false;
        }

        $this->userService->transitionStatus($admin, $author, UserStatus::Blocked);
        $this->resolveSubject($report, ModerationReportStatus::Actioned, $admin, $note);

        return true;
    }

    public function suspendAuthor(ModerationReport $report, User $admin, int $days, ?string $note = null): bool
    {
        $author = $report->getAuthor();
        $isValidLength = $days >= 1 && $days <= self::MAX_SUSPENSION_DAYS;
        if ($author === null || !$isValidLength || !$this->canBlockAuthor($report, $admin)) {
            return false;
        }

        $until = $this->clock->now()->modify(sprintf('+%d days', $days));
        $this->userService->transitionStatus($admin, $author, UserStatus::Blocked, $until);
        $this->resolveSubject($report, ModerationReportStatus::Actioned, $admin, $note);

        return true;
    }

    public function warnAuthor(ModerationReport $report, User $admin, string $note): bool
    {
        $cleanNote = $this->cleanNote($note);
        if ($cleanNote === null || !$this->canWarnAuthor($report, $admin)) {
            return false;
        }

        $this->warningEmail->send(['report' => $report, 'note' => $cleanNote]);
        $this->resolveSubject($report, ModerationReportStatus::Actioned, $admin, $cleanNote);

        return true;
    }

    private function resolveSubject(ModerationReport $report, ModerationReportStatus $status, User $admin, ?string $note): void
    {
        $now = $this->clock->now();
        $cleanNote = $this->cleanNote($note);
        $openReports = $this->repository->findOpenForSubject($report->getSubjectType(), $report->getSubjectId());
        foreach ($openReports as $openReport) {
            $openReport->resolve($status, $admin, $now, $cleanNote);
        }
        $this->em->flush();
    }

    private function cleanNote(?string $note): ?string
    {
        $clean = $this->sanitizer->toPlainText(trim((string) $note));

        return $clean === '' ? null : $clean;
    }

    private function providerFor(string $subjectType): ?SubjectProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->getTypeKey() === $subjectType) {
                return $provider;
            }
        }

        return null;
    }
}
