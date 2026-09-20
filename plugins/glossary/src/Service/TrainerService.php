<?php declare(strict_types=1);

namespace Plugin\Glossary\Service;

use App\Item\Tag\FacetService;
use App\Item\Tag\TagService;
use App\Repository\ItemTagAssignmentRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Entity\TrainerCard;
use Plugin\Glossary\Entity\TrainerDay;
use Plugin\Glossary\Enum\AnswerMode;
use Plugin\Glossary\Enum\CardState;
use Plugin\Glossary\Enum\Direction;
use Plugin\Glossary\Enum\Grade;
use Plugin\Glossary\Enum\MatchResult;
use Plugin\Glossary\Enum\Mode;
use Plugin\Glossary\Enum\Scope;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\Repository\TrainerCardRepository;
use Plugin\Glossary\Repository\TrainerDayRepository;
use Plugin\Glossary\ValueObject\Config;
use Plugin\Glossary\ValueObject\Question;
use Plugin\Glossary\ValueObject\TrainerSession;
use Random\Engine\Mt19937;
use Random\Randomizer;

class TrainerService
{
    public const int CHOICE_COUNT = 4;
    private const int WORST_LIMIT = 50;
    private const int DISTRACTOR_SAMPLE = 30;

    /** @var list<int>|null */
    private ?array $visible = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TrainerCardRepository $cardRepo,
        private readonly TrainerDayRepository $dayRepo,
        private readonly GlossaryService $glossaryService,
        private readonly ConfigService $configService,
        private readonly SchedulerInterface $scheduler,
        private readonly AnswerMatcher $matcher,
        private readonly TagService $tagService,
        private readonly FacetService $facetService,
        private readonly ItemTagAssignmentRepository $assignmentRepo,
    ) {}

    public function isEnabled(): bool
    {
        return $this->config()->isTrainerEnabled();
    }

    public function config(): Config
    {
        return $this->configService->getConfig();
    }

    /**
     * @param list<int> $tagIds
     * @return list<int>
     */
    public function scopeIds(Scope $scope, array $tagIds, int $userId, DateTimeImmutable $now): array
    {
        $visible = $this->visibleIds();

        $ids = match ($scope) {
            Scope::Selection => $tagIds === []
                ? $visible
                : array_values(array_intersect($visible, $this->assignmentRepo->itemIdsWithAllTags(GlossaryTaggableTypeProvider::ITEM_TYPE, $tagIds))),
            Scope::Due => $this->cardRepo->dueGlossaryIds($userId, null, $visible, $now),
            Scope::Starred => array_values(array_intersect($visible, $this->cardRepo->markedGlossaryIds($userId))),
            Scope::Worst => $this->cardRepo->worstGlossaryIds($userId, $visible, self::WORST_LIMIT),
            Scope::Unseen => array_values(array_diff($visible, $this->cardRepo->seenGlossaryIds($userId))),
        };

        return array_values(array_diff($ids, $this->cardRepo->suspendedGlossaryIds($userId)));
    }

    /** @return list<array{scope: Scope, count: int}> */
    public function standingScopes(int $userId, DateTimeImmutable $now): array
    {
        $scopes = [];
        foreach ([Scope::Due, Scope::Starred, Scope::Worst, Scope::Unseen] as $scope) {
            $scopes[] = ['scope' => $scope, 'count' => count($this->scopeIds($scope, [], $userId, $now))];
        }

        return $scopes;
    }

    /** @param list<int> $tagIds */
    public function start(
        int $userId,
        Scope $scope,
        array $tagIds,
        Mode $mode,
        Direction $direction,
        AnswerMode $answerMode,
        int $size,
        DateTimeImmutable $now,
    ): TrainerSession {
        $candidates = $this->servable($this->scopeIds($scope, $tagIds, $userId, $now), $direction);

        if ($mode === Mode::Review) {
            $queue = $this->reviewQueue($userId, $scope, $direction, $candidates, $now);
        } else {
            $queue = $candidates;
            shuffle($queue);
        }
        $queue = array_slice($queue, 0, max(Config::SESSION_SIZE_MIN, min(Config::SESSION_SIZE_MAX, $size)));

        return new TrainerSession($scope, $tagIds, $mode, $direction, $answerMode, $queue, random_int(1, 2_000_000_000), count($queue));
    }

    public function question(TrainerSession $session, int $userId): ?Question
    {
        foreach ($session->queue as $glossaryId) {
            $entry = $this->entry($glossaryId);
            if ($entry !== null && $this->canServe($entry, $session->direction)) {
                return $this->buildQuestion($session, $entry, $userId);
            }
        }

        return null;
    }

    public function answer(
        TrainerSession $session,
        int $userId,
        int $glossaryId,
        ?Grade $grade,
        ?string $typed,
        ?int $choiceId,
        DateTimeImmutable $now,
    ): TrainerSession {
        if (!in_array($glossaryId, $session->queue, true)) {
            return $session;
        }

        $entry = $this->entry($glossaryId);
        if ($entry === null || !$this->canServe($entry, $session->direction)) {
            return $session->withSkipped($glossaryId);
        }

        $expected = $this->answerFor($entry, $session->direction);
        $graded = $this->grade($session, $expected, $grade, $typed, $choiceId);
        if ($graded === null) {
            return $session;
        }
        [$result, $grade, $given] = $graded;

        $this->record($userId, $entry, $session->direction, $grade, $session->mode, $now);

        return $session->withAnswer(
            $glossaryId,
            $grade->isPass(),
            $session->answerMode === AnswerMode::Flip
                ? null
                : [
                    'verdict' => $result->value,
                    'prompt' => $this->promptFor($entry, $session->direction),
                    'expected' => $expected,
                    'given' => $given,
                ],
        );
    }

    /**
     * @param list<int> $ids
     * @return list<Glossary> in the given order, visible entries only
     */
    public function entries(array $ids): array
    {
        $entries = $this->glossaryService->getByIds($ids);

        return array_values(array_filter(array_map(static fn(int $id): ?Glossary => $entries[$id] ?? null, $ids)));
    }

    public function toggleMarked(int $userId, int $glossaryId, DateTimeImmutable $now): ?bool
    {
        $cards = $this->entryCards($userId, $glossaryId, $now);
        if ($cards === null) {
            return null;
        }

        $marked = !array_any($cards, static fn(TrainerCard $card): bool => $card->isMarked());
        foreach ($cards as $card) {
            $card->setMarked($marked);
        }
        $this->em->flush();

        return $marked;
    }

    public function toggleSuspended(int $userId, int $glossaryId, DateTimeImmutable $now): ?bool
    {
        $cards = $this->entryCards($userId, $glossaryId, $now);
        if ($cards === null) {
            return null;
        }

        $suspended = !array_any($cards, static fn(TrainerCard $card): bool => $card->isSuspended());
        foreach ($cards as $card) {
            $card->setSuspended($suspended);
        }
        $this->em->flush();

        return $suspended;
    }

    /** @return list<int> */
    public function visibleIds(): array
    {
        return $this->visible ??= $this->facetService->withoutFacets($this->glossaryService->getVisibleIds(...));
    }

    private function entry(int $glossaryId): ?Glossary
    {
        return $this->glossaryService->getByIds([$glossaryId])[$glossaryId] ?? null;
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function servable(array $ids, Direction $direction): array
    {
        $entries = $this->glossaryService->getByIds($ids);

        return array_values(array_filter($ids, fn(int $id): bool => isset($entries[$id]) && $this->canServe($entries[$id], $direction)));
    }

    /**
     * @param list<int> $candidates
     * @return list<int>
     */
    private function reviewQueue(int $userId, Scope $scope, Direction $direction, array $candidates, DateTimeImmutable $now): array
    {
        $due = $this->cardRepo->dueGlossaryIds($userId, $direction, $candidates, $now);
        if ($scope === Scope::Due) {
            return $due;
        }

        $startedToday = $this->dayRepo->findDay($userId, $now)?->getNewStarted() ?? 0;
        $allowance = max(0, $this->config()->getNewCardsPerDay() - $startedToday);
        $scheduled = $this->cardRepo->scheduledGlossaryIds($userId, $direction, $candidates);
        $fresh = array_slice(array_values(array_diff($candidates, $scheduled)), 0, $allowance);

        return [...$due, ...$fresh];
    }

    private function canServe(Glossary $entry, Direction $direction): bool
    {
        return $this->promptFor($entry, $direction) !== '' && $this->answerFor($entry, $direction) !== '';
    }

    private function promptFor(Glossary $entry, Direction $direction): string
    {
        return match ($direction) {
            Direction::TermToDefinition => (string) $entry->getPhrase(),
            Direction::DefinitionToTerm => $this->glossaryService->definitionFor($entry),
            Direction::SecondaryToTerm => (string) $entry->getSecondary(),
        };
    }

    private function answerFor(Glossary $entry, Direction $direction): string
    {
        return $direction === Direction::TermToDefinition ? $this->glossaryService->definitionFor($entry) : (string) $entry->getPhrase();
    }

    private function buildQuestion(TrainerSession $session, Glossary $entry, int $userId): Question
    {
        $direction = $session->direction;
        $termLanguage = $entry->getTermLanguage();
        $showSecondary = $direction !== Direction::SecondaryToTerm && $this->config()->isSecondaryEnabled();

        return new Question(
            entry: $entry,
            prompt: $this->promptFor($entry, $direction),
            answer: $this->answerFor($entry, $direction),
            promptLanguage: $direction === Direction::DefinitionToTerm ? null : $termLanguage,
            answerLanguage: $direction->answersWithTerm() ? $termLanguage : null,
            secondary: $showSecondary ? $entry->getSecondary() : null,
            choices: $session->answerMode === AnswerMode::Choice ? $this->choices($entry, $session) : [],
            marked: array_any($this->cardRepo->findForEntry($userId, (int) $entry->getId()), static fn(TrainerCard $card): bool => $card->isMarked()),
        );
    }

    /** @return list<array{id: int, text: string}> */
    private function choices(Glossary $entry, TrainerSession $session): array
    {
        $id = (int) $entry->getId();
        $randomizer = new Randomizer(new Mt19937(($session->seed + $id) % 2_147_483_647));
        $picked = [$id => $this->answerFor($entry, $session->direction)];

        foreach ($this->distractorPools($id) as $pool) {
            $sample = array_slice($randomizer->shuffleArray($pool), 0, self::DISTRACTOR_SAMPLE);
            $candidates = $this->glossaryService->getByIds($sample);
            foreach ($sample as $candidateId) {
                if (count($picked) >= self::CHOICE_COUNT) {
                    break 2;
                }

                $text = isset($candidates[$candidateId]) ? $this->answerFor($candidates[$candidateId], $session->direction) : '';
                $isDuplicate = array_any($picked, fn(string $existing): bool => $this->matcher->same($existing, $text));
                if ($text !== '' && !$isDuplicate) {
                    $picked[$candidateId] = $text;
                }
            }
        }

        $choices = [];
        foreach ($picked as $choiceId => $text) {
            $choices[] = ['id' => $choiceId, 'text' => $text];
        }

        return array_values($randomizer->shuffleArray($choices));
    }

    /** @return list<list<int>> candidate ids, the entry's most specific tag branch first and the whole visible list last */
    private function distractorPools(int $glossaryId): array
    {
        $visible = $this->visibleIds();
        $depths = $this->tagService->getDepths(GlossaryTaggableTypeProvider::ITEM_TYPE);
        $tagIds = $this->assignmentRepo->tagIdsFor(GlossaryTaggableTypeProvider::ITEM_TYPE, $glossaryId);
        usort($tagIds, static fn(int $a, int $b): int => ($depths[$b] ?? 0) <=> ($depths[$a] ?? 0));

        $pools = [];
        foreach ($tagIds as $tagId) {
            $branch = $this->assignmentRepo->itemIdsWithAllTags(GlossaryTaggableTypeProvider::ITEM_TYPE, [$tagId]);
            $pools[] = array_values(array_diff(array_intersect($branch, $visible), [$glossaryId]));
        }
        $pools[] = array_values(array_diff($visible, [$glossaryId]));

        return $pools;
    }

    /** @return array{0: MatchResult, 1: Grade, 2: ?string}|null */
    private function grade(TrainerSession $session, string $expected, ?Grade $grade, ?string $typed, ?int $choiceId): ?array
    {
        if ($session->answerMode === AnswerMode::Flip) {
            return $grade === null ? null : [$grade->isPass() ? MatchResult::Exact : MatchResult::Wrong, $grade, null];
        }

        if ($session->answerMode === AnswerMode::Choice) {
            $chosen = $choiceId === null ? null : $this->entry($choiceId);
            $given = $chosen === null ? '' : $this->answerFor($chosen, $session->direction);
            $result = $given !== '' && $this->matcher->same($given, $expected) ? MatchResult::Exact : MatchResult::Wrong;

            return [$result, $result->grade(), $given];
        }

        $given = trim((string) $typed);
        $result = $this->matcher->match($given, $expected);

        return [$result, $result->grade(), $given];
    }

    private function record(int $userId, Glossary $entry, Direction $direction, Grade $grade, Mode $mode, DateTimeImmutable $now): void
    {
        $card = $this->cardFor($userId, $entry, $direction, $now);
        $startedNew = $mode === Mode::Review && $card->getState() === CardState::New;

        $card->recordAnswer($grade->isPass(), $now);
        if ($mode === Mode::Review) {
            $this->scheduler->schedule($card, $grade, $now);
        }

        $day = $this->dayRepo->findDay($userId, $now);
        if ($day === null) {
            $day = new TrainerDay($userId, $now);
            $this->em->persist($day);
        }
        $day->record($grade->isPass(), $startedNew);

        $this->em->flush();
    }

    private function cardFor(int $userId, Glossary $entry, Direction $direction, DateTimeImmutable $now): TrainerCard
    {
        $card = $this->cardRepo->findOneFor($userId, (int) $entry->getId(), $direction);
        if ($card !== null) {
            return $card;
        }

        $siblings = $this->cardRepo->findForEntry($userId, (int) $entry->getId());
        $card = new TrainerCard($userId, $entry, $direction, $now);
        $card->setMarked(array_any($siblings, static fn(TrainerCard $sibling): bool => $sibling->isMarked()));
        $card->setSuspended(array_any($siblings, static fn(TrainerCard $sibling): bool => $sibling->isSuspended()));
        $this->em->persist($card);

        return $card;
    }

    /** @return list<TrainerCard>|null null when the entry is not visible to this member */
    private function entryCards(int $userId, int $glossaryId, DateTimeImmutable $now): ?array
    {
        $entry = $this->entry($glossaryId);
        if ($entry === null) {
            return null;
        }

        $cards = $this->cardRepo->findForEntry($userId, $glossaryId);
        if ($cards === []) {
            $cards = [$this->cardFor($userId, $entry, $this->config()->getOfferedDirections()[0], $now)];
        }

        return $cards;
    }
}
