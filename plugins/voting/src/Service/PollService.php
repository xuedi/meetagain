<?php declare(strict_types=1);

namespace Plugin\Voting\Service;

use App\Entity\Event;
use App\Item\ItemCandidateProviderInterface;
use App\Service\Item\ItemAssociationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Voting\Entity\Poll;
use Plugin\Voting\Entity\PollOption;
use Plugin\Voting\Entity\PollStatus;
use Plugin\Voting\Entity\Vote;
use Plugin\Voting\Repository\PollRepository;
use Plugin\Voting\Repository\VoteRepository;
use Plugin\Voting\ValueObject\PollClosure;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class PollService
{
    /**
     * @param iterable<ItemCandidateProviderInterface> $candidateProviders
     */
    public function __construct(
        private EntityManagerInterface $em,
        private PollRepository $pollRepo,
        private VoteRepository $voteRepo,
        private ItemAssociationService $itemAssociations,
        private VotingConfigService $config,
        #[AutowireIterator(ItemCandidateProviderInterface::class)]
        private iterable $candidateProviders,
    ) {}

    /**
     * @param list<int> $itemIds candidate item ids for the ballot
     */
    public function create(Event $event, string $itemType, array $itemIds, int $durationDays, int $createdBy): Poll
    {
        $itemIds = array_values(array_unique(array_filter($itemIds)));
        if ($itemIds === []) {
            throw new RuntimeException('voting_poll.flash_no_candidates');
        }

        $createdAt = new DateTimeImmutable();
        $poll = new Poll();
        $poll->setEvent($event);
        $poll->setItemType($itemType);
        $poll->setCreatedBy($createdBy);
        $poll->setCreatedAt($createdAt);
        $poll->setDurationDays($durationDays);
        $poll->setEndDate($createdAt->modify('+' . $durationDays . ' days'));
        $poll->setStatus(PollStatus::Active);

        foreach ($itemIds as $itemId) {
            $option = new PollOption();
            $option->setItemId($itemId);
            $poll->addOption($option);
        }

        $this->em->persist($poll);
        $this->em->flush();

        return $poll;
    }

    /**
     * Replaces the user's full approval set in one transaction. Single-choice config caps the
     * ballot to the first selection.
     *
     * @param list<int> $selectedItemIds
     */
    public function castVote(int $userId, Poll $poll, array $selectedItemIds): void
    {
        if ($poll->getStatus() !== PollStatus::Active) {
            throw new RuntimeException('voting_poll.flash_poll_closed');
        }

        $allowed = $poll->getOptionItemIds();
        $selectedItemIds = array_values(array_unique(array_filter($selectedItemIds, static fn(int $id): bool => in_array($id, $allowed, true))));

        if ($this->config->getConfig()->isSingleChoice()) {
            $selectedItemIds = array_slice($selectedItemIds, 0, 1);
        }

        $this->voteRepo->deleteByPollAndUser((int) $poll->getId(), $userId);

        foreach ($selectedItemIds as $itemId) {
            $vote = new Vote();
            $vote->setPoll($poll);
            $vote->setUserId($userId);
            $vote->setItemId($itemId);
            $vote->setVotedAt(new DateTimeImmutable());
            $this->em->persist($vote);
        }

        $this->em->flush();
    }

    public function close(Poll $poll): PollClosure
    {
        if ($poll->getStatus() !== PollStatus::Active) {
            throw new RuntimeException('voting_poll.flash_already_closed');
        }

        $poll->setStatus(PollStatus::Closed);
        $poll->setClosedAt(new DateTimeImmutable());

        $voteCounts = $this->voteRepo->countVotesPerItem((int) $poll->getId());
        // Only ballot options can win, even if stray votes exist for other ids.
        $voteCounts = array_intersect_key($voteCounts, array_flip($poll->getOptionItemIds()));

        if ($voteCounts === []) {
            $this->em->persist($poll);
            $this->em->flush();

            return new PollClosure(null, []);
        }

        $maxVotes = max($voteCounts);
        $leadingItemIds = array_values(array_keys(array_filter($voteCounts, static fn(int $c): bool => $c === $maxVotes)));

        if (count($leadingItemIds) === 1) {
            $winner = $leadingItemIds[0];
            $poll->setWinningItemId($winner);
            $poll->setTiedItemIds(null);
            $this->em->persist($poll);
            $this->em->flush();

            return new PollClosure($winner, []);
        }

        $poll->setWinningItemId(null);
        $poll->setTiedItemIds($leadingItemIds);
        $this->em->persist($poll);
        $this->em->flush();

        return new PollClosure(null, $leadingItemIds);
    }

    /**
     * Commits the outcome after a tie-break or clean single-winner close: records the winner on
     * the poll and attaches it to the event through the core association seam. The attach
     * dispatches CreateEventItemAssociation, which the wishlist subsystem reacts to for backlog
     * aging - voting never references wishlist directly.
     */
    public function commitOutcome(Poll $poll, int $chosenItemId): void
    {
        $poll->setWinningItemId($chosenItemId);
        $this->em->persist($poll);
        $this->em->flush();

        $this->itemAssociations->attach((int) $poll->getEventId(), (string) $poll->getItemType(), $chosenItemId, (int) $poll->getCreatedBy());
    }

    /**
     * Ranked candidate item ids for a type, from the core candidate-provider union chain.
     *
     * @return list<int>
     */
    public function getCandidateItemIds(string $itemType): array
    {
        $ordered = [];
        foreach ($this->candidateProviders as $provider) {
            foreach ($provider->getCandidateItemIds($itemType) as $itemId) {
                $ordered[$itemId] = true;
            }
        }

        return array_map('intval', array_keys($ordered));
    }

    /** @return Poll[] */
    public function getActivePolls(): array
    {
        return $this->pollRepo->findActive();
    }

    /** @return Poll[] */
    public function getClosedPolls(): array
    {
        return $this->pollRepo->findClosed();
    }

    public function get(int $id): ?Poll
    {
        return $this->pollRepo->find($id);
    }

    /** @return array<int, int> item_id => vote_count */
    public function getVoteCounts(Poll $poll): array
    {
        return $this->voteRepo->countVotesPerItem((int) $poll->getId());
    }

    public function hasUserVoted(Poll $poll, int $userId): bool
    {
        return $this->voteRepo->hasUserVoted((int) $poll->getId(), $userId);
    }

    /** @return list<int> item ids the user has approved on this poll */
    public function getUserVotedItemIds(Poll $poll, int $userId): array
    {
        return array_map(static fn(Vote $v): int => (int) $v->getItemId(), $this->voteRepo->findByPollAndUser((int) $poll->getId(), $userId));
    }
}
