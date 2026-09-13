<?php declare(strict_types=1);

namespace Plugin\Photos\Service;

use App\Item\FilterService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Ballot\Contract\BallotInterface;
use Module\Ballot\Contract\BallotRequest;
use Module\Ballot\Contract\BallotStatus;
use Module\Ballot\Contract\BallotView;
use Module\Ballot\Contract\Candidate;
use Module\Ballot\Contract\SettlementMode;
use Module\Ballot\Contract\TallyMode;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Repository\PhotoRepository;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ContestService
{
    public const string PURPOSE = 'photo.contest';
    public const int DURATION_DAYS = 14;
    private const int MINIMUM_ENTRIES = 2;

    public function __construct(
        private PhotoRepository $photoRepo,
        private ConfigService $configService,
        private FilterService $itemFilter,
        private BallotInterface $ballots,
        private TranslatorInterface $translator,
        private EntityManagerInterface $em,
    ) {}

    public function isLive(): bool
    {
        return $this->configService->getConfig()->isContest();
    }

    public function submit(Photo $photo): void
    {
        if ($photo->isContestSubmitted()) {
            return;
        }

        if ($this->remainingFor((int) $photo->getCreatedBy()) < 1) {
            throw new RuntimeException('photos_contest.flash_cap_reached');
        }

        $photo->setContestSubmitted(true);
        $this->em->flush();
    }

    public function withdraw(Photo $photo): void
    {
        $photo->setContestSubmitted(false);
        $this->em->flush();
    }

    public function remainingFor(int $userId): int
    {
        $submitted = $this->photoRepo->countSubmittedByCreator($userId, $this->allowedIds());

        return max(0, $this->configService->getConfig()->getContestSubmissionsPerMember() - $submitted);
    }

    /** @return list<int> */
    public function getQueuedIds(): array
    {
        return $this->photoRepo->findSubmittedIds($this->allowedIds());
    }

    public function getOpenContest(?int $viewerUserId = null): ?BallotView
    {
        foreach ($this->contests($viewerUserId) as $contest) {
            if (!$contest->status->isResolved()) {
                return $contest;
            }
        }

        return null;
    }

    /** @return list<BallotView> */
    public function getFinishedContests(?int $viewerUserId = null): array
    {
        return array_values(array_filter(
            $this->contests($viewerUserId),
            static fn(BallotView $contest): bool => $contest->status === BallotStatus::Settled,
        ));
    }

    public function start(int $createdBy): int
    {
        if ($this->getOpenContest() instanceof BallotView) {
            throw new RuntimeException('photos_contest.flash_already_open');
        }

        $queued = $this->getQueuedIds();
        if ($queued === []) {
            throw new RuntimeException('photos_contest.flash_no_entries');
        }
        if (count($queued) < self::MINIMUM_ENTRIES) {
            throw new RuntimeException('photos_contest.flash_too_few_entries');
        }

        $ballotId = $this->ballots->open($this->request($queued, $createdBy));
        $this->photoRepo->clearSubmitted($queued);

        return $ballotId;
    }

    /**
     * @return list<BallotView>
     */
    private function contests(?int $viewerUserId): array
    {
        return $this->ballots->listForPurpose(self::PURPOSE, $viewerUserId);
    }

    /**
     * @param list<int> $photoIds
     */
    private function request(array $photoIds, int $createdBy): BallotRequest
    {
        $candidates = [];
        foreach ($photoIds as $photoId) {
            $candidates[] = new Candidate((string) $photoId, '#' . $photoId);
        }

        return new BallotRequest(
            self::PURPOSE,
            $candidates,
            new DateTimeImmutable('+' . self::DURATION_DAYS . ' days'),
            $createdBy,
            null,
            TallyMode::Single,
            SettlementMode::Automatic,
            $this->translator->trans('photos_contest.ballot_title'),
        );
    }

    /** @return list<int>|null */
    private function allowedIds(): ?array
    {
        return $this->itemFilter->getAllowedItemIds(PhotoService::ITEM_TYPE);
    }
}
