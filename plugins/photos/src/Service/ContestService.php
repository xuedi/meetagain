<?php declare(strict_types=1);

namespace Plugin\Photos\Service;

use App\Item\FilterService;
use App\Service\Config\PluginService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Repository\PhotoRepository;
use Plugin\Voting\Entity\Poll;
use Plugin\Voting\Service\PollService;
use RuntimeException;

readonly class ContestService
{
    public const string VOTING_PLUGIN = 'voting';
    public const int DURATION_DAYS = 14;
    private const int DEMO_PHOTOS_NEEDED = 8;
    private const int DEMO_VOTERS_NEEDED = 3;
    private const int DEMO_ROUND = 3;
    private const int DEMO_FINISHED_MAX = 2;
    private const int DEMO_OPEN_MIN = 3;
    private const int DEMO_OPEN_MAX = 4;
    private const int DEMO_QUEUED = 2;

    public function __construct(
        private PhotoRepository $photoRepo,
        private ConfigService $configService,
        private FilterService $itemFilter,
        private PluginService $pluginService,
        private PollService $pollService,
        private EntityManagerInterface $em,
    ) {}

    public function isLive(): bool
    {
        return $this->configService->getConfig()->isContest() && in_array(self::VOTING_PLUGIN, $this->pluginService->getActiveList(), true);
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

    public function getOpenContest(): ?Poll
    {
        foreach ($this->pollService->getActivePolls() as $poll) {
            if ($poll->getEventId() === null && $poll->getItemType() === PhotoService::ITEM_TYPE) {
                return $poll;
            }
        }

        return null;
    }

    /** @return list<Poll> */
    public function getFinishedContests(): array
    {
        $finished = [];
        foreach ($this->pollService->getClosedPolls() as $poll) {
            if (!($poll->getEventId() === null && $poll->getItemType() === PhotoService::ITEM_TYPE)) {
                continue;
            }

            $finished[] = $poll;
        }

        return $finished;
    }

    public function start(int $createdBy): Poll
    {
        if ($this->getOpenContest() instanceof Poll) {
            throw new RuntimeException('photos_contest.flash_already_open');
        }

        $queued = $this->getQueuedIds();
        if ($queued === []) {
            throw new RuntimeException('photos_contest.flash_no_entries');
        }

        $poll = $this->pollService->create(null, PhotoService::ITEM_TYPE, $queued, self::DURATION_DAYS, $createdBy);
        $this->photoRepo->clearSubmitted($queued);

        return $poll;
    }

    public function isSeedable(): bool
    {
        return in_array(self::VOTING_PLUGIN, $this->pluginService->getActiveList(), true)
            && $this->getOpenContest() === null
            && $this->getFinishedContests() === [];
    }

    /** @param list<int> $photoIds */
    public function seedDemo(array $photoIds): bool
    {
        $voters = $this->distinctCreators($photoIds);
        if (count($photoIds) < self::DEMO_PHOTOS_NEEDED || count($voters) < self::DEMO_VOTERS_NEEDED) {
            return false;
        }

        $rest = array_slice($photoIds, 0, -self::DEMO_QUEUED);
        $rounds = min(self::DEMO_FINISHED_MAX, intdiv(count($rest) - self::DEMO_OPEN_MIN, self::DEMO_ROUND));
        for ($round = 0; $round < $rounds; $round++) {
            $this->seedFinished(array_slice($rest, $round * self::DEMO_ROUND, self::DEMO_ROUND), $voters, $rounds - $round);
        }

        $this->seedOpen(array_slice($rest, $rounds * self::DEMO_ROUND, self::DEMO_OPEN_MAX), $voters);
        $this->seedQueue(array_slice($photoIds, -self::DEMO_QUEUED));

        return true;
    }

    /**
     * @param list<int> $options
     * @param list<int> $voters
     * @param int       $monthsAgo so a club's rounds read as a history rather than as one seeding run
     */
    private function seedFinished(array $options, array $voters, int $monthsAgo): void
    {
        $poll = $this->pollService->create(null, PhotoService::ITEM_TYPE, $options, self::DURATION_DAYS, $voters[0]);

        $runnerUp = array_key_last($voters);
        foreach ($voters as $index => $userId) {
            $this->pollService->castVote($userId, $poll, [$index === $runnerUp ? $options[1] : $options[0]]);
        }

        $closure = $this->pollService->close($poll);
        if ($closure->winningItemId === null) {
            return;
        }

        $this->pollService->commitOutcome($poll, $closure->winningItemId);

        $closedAt = new DateTimeImmutable(sprintf('-%d months', $monthsAgo));
        $poll->setEndDate($closedAt)->setClosedAt($closedAt);
        $this->em->flush();
    }

    /**
     * @param list<int> $options
     * @param list<int> $voters
     */
    private function seedOpen(array $options, array $voters): void
    {
        $poll = $this->pollService->create(null, PhotoService::ITEM_TYPE, $options, self::DURATION_DAYS, $voters[0]);

        foreach (array_slice($voters, 0, 3) as $index => $userId) {
            $this->pollService->castVote($userId, $poll, [$options[$index === 0 ? 0 : 1]]);
        }
    }

    /** @param list<int> $photoIds */
    private function seedQueue(array $photoIds): void
    {
        foreach ($photoIds as $photoId) {
            $photo = $this->photoRepo->find($photoId);
            if ($photo instanceof Photo) {
                $photo->setContestSubmitted(true);
            }
        }

        $this->em->flush();
    }

    /**
     * @param  list<int> $photoIds
     * @return list<int> distinct uploader ids, in the order their photos appear
     */
    private function distinctCreators(array $photoIds): array
    {
        $creators = [];
        foreach ($photoIds as $photoId) {
            $createdBy = $this->photoRepo->find($photoId)?->getCreatedBy();
            if ($createdBy !== null) {
                $creators[$createdBy] = true;
            }
        }

        return array_map(intval(...), array_keys($creators));
    }

    /** @return list<int>|null */
    private function allowedIds(): ?array
    {
        return $this->itemFilter->getAllowedItemIds(PhotoService::ITEM_TYPE);
    }
}
