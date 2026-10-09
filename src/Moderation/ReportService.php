<?php declare(strict_types=1);

namespace App\Moderation;

use App\Activity\ActivityService;
use App\Activity\Messages\ReportedSubject;
use App\Entity\ModerationReport;
use App\Entity\User;
use App\Enum\ModerationReportReason;
use App\Enum\ModerationReportStatus;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Repository\ModerationReportRepository;
use App\Service\Member\UserService;
use App\Service\Security\ContentSanitizer;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class ReportService
{
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
        $report = new ModerationReport($subjectType, $subjectId, $reason, new DateTimeImmutable())
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
        $author = $report->getAuthor();
        if ($author === null || $author->getId() === $admin->getId() || $author->getRole() === UserRole::System) {
            return false;
        }

        return $author->getStatus() === UserStatus::Active;
    }

    public function dismiss(ModerationReport $report, User $admin): void
    {
        $this->resolveSubject($report, ModerationReportStatus::Dismissed, $admin);
    }

    public function removeSubject(ModerationReport $report, User $admin): void
    {
        $provider = $this->providerFor($report->getSubjectType());
        if ($provider?->getRemoveLabelKey() === null) {
            return;
        }

        $provider->remove($report->getSubjectId());
        $this->resolveSubject($report, ModerationReportStatus::Actioned, $admin);
    }

    public function blockAuthor(ModerationReport $report, User $admin): void
    {
        $author = $report->getAuthor();
        if ($author === null || !$this->canBlockAuthor($report, $admin)) {
            return;
        }

        $this->userService->transitionStatus($admin, $author, UserStatus::Blocked);
        $this->resolveSubject($report, ModerationReportStatus::Actioned, $admin);
    }

    private function resolveSubject(ModerationReport $report, ModerationReportStatus $status, User $admin): void
    {
        $now = new DateTimeImmutable();
        $openReports = $this->repository->findOpenForSubject($report->getSubjectType(), $report->getSubjectId());
        foreach ($openReports as $openReport) {
            $openReport->resolve($status, $admin, $now);
        }
        $this->em->flush();
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
