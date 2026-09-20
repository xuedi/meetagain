<?php declare(strict_types=1);

namespace Plugin\Glossary\Portability;

use App\Entity\ChangeProposal;
use App\Entity\ItemTag;
use App\Entity\User;
use App\Enum\ChangeProposalStatus;
use App\Enum\FieldResolution;
use App\Portability\DataCategory;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\PluginSectionInterface;
use App\Portability\Scope;
use App\Repository\UserRepository;
use App\Review\FieldChange;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Entity\TrainerCard;
use Plugin\Glossary\Entity\TrainerDay;
use Plugin\Glossary\Enum\CardState;
use Plugin\Glossary\Enum\Direction;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\Review\GlossaryChangeTarget;
use Plugin\Glossary\Service\GlossaryService;

readonly class MemberSection implements PluginSectionInterface
{
    public const string KIND_CARDS = 'glossary_cards';
    public const string KIND_DAYS = 'glossary_days';
    public const string KIND_CHANGE_PROPOSALS = 'glossary_change_proposals';

    private const string ITEM_TYPE = GlossaryTaggableTypeProvider::ITEM_TYPE;

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private GlossaryService $glossaryService,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'glossary';
    }

    #[Override]
    public function getOrder(): int
    {
        return 100;
    }

    #[Override]
    public function getPluginKey(): string
    {
        return 'glossary';
    }

    #[Override]
    public function getKindLabels(): array
    {
        return [
            self::KIND_CARDS => 'glossary_portability.kind_cards',
            self::KIND_DAYS => 'glossary_portability.kind_days',
            self::KIND_CHANGE_PROPOSALS => 'glossary_portability.kind_change_proposals',
        ];
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        $entryIds = $scope->itemIds[self::ITEM_TYPE] ?? [];
        if ($entryIds === []) {
            return [];
        }

        $cards = array_values(array_filter(
            $this->em->getRepository(TrainerCard::class)->findBy(['glossary' => $entryIds], ['id' => 'ASC']),
            static fn(TrainerCard $card): bool => $scope->grantsId($card->getUserId(), DataCategory::Collections),
        ));
        $emails = $this->emailsById(array_map(static fn(TrainerCard $card): int => $card->getUserId(), $cards));

        $cardRows = array_map(fn(TrainerCard $card): array => $this->cardRow($card, $emails), $cards);
        $dayRows = $this->dayRows($emails);
        $proposalRows = $this->proposalRows($scope, $entryIds);

        if ($cardRows === [] && $dayRows === [] && $proposalRows === []) {
            return [];
        }

        return [
            'cards' => $cardRows,
            'days' => $dayRows,
            'change_proposals' => $proposalRows,
        ];
    }

    #[Override]
    public function import(array $rows, ImportContext $context): void
    {
        foreach ($this->rowsOf($rows, 'cards') as $row) {
            $this->importCard($row, $context);
        }

        foreach ($this->rowsOf($rows, 'days') as $row) {
            $this->importDay($row, $context);
        }

        foreach ($this->rowsOf($rows, 'change_proposals') as $row) {
            $this->importProposal($row, $context);
        }
    }

    /**
     * @param array<int, string> $emails
     * @return array<string, mixed>
     */
    private function cardRow(TrainerCard $card, array $emails): array
    {
        return [
            'email' => $emails[$card->getUserId()] ?? null,
            'glossary_ref' => $card->getGlossary()->getId(),
            'direction' => $card->getDirection()->value,
            'state' => $card->getState()->value,
            'due_at' => $card->getDueAt()?->format(DateTimeInterface::ATOM),
            'interval_days' => $card->getIntervalDays(),
            'ease_permille' => $card->getEasePermille(),
            'repetitions' => $card->getRepetitions(),
            'lapses' => $card->getLapses(),
            'times_seen' => $card->getTimesSeen(),
            'times_correct' => $card->getTimesCorrect(),
            'marked' => $card->isMarked(),
            'suspended' => $card->isSuspended(),
            'last_reviewed_at' => $card->getLastReviewedAt()?->format(DateTimeInterface::ATOM),
            'created_at' => $card->getCreatedAt()->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<int, string> $emails
     * @return list<array<string, mixed>>
     */
    private function dayRows(array $emails): array
    {
        if ($emails === []) {
            return [];
        }

        $rows = [];
        foreach ($this->em->getRepository(TrainerDay::class)->findBy(['userId' => array_keys($emails)], ['id' => 'ASC']) as $day) {
            $rows[] = [
                'email' => $emails[$day->getUserId()] ?? null,
                'day' => $day->getDay()->setTime(0, 0)->format(DateTimeInterface::ATOM),
                'reviewed' => $day->getReviewed(),
                'correct' => $day->getCorrect(),
                'new_started' => $day->getNewStarted(),
            ];
        }

        usort($rows, static fn(array $a, array $b): int => [$a['email'], $a['day']] <=> [$b['email'], $b['day']]);

        return $rows;
    }

    /**
     * @param list<int> $entryIds
     * @return list<array<string, mixed>>
     */
    private function proposalRows(Scope $scope, array $entryIds): array
    {
        $proposals = $this->em->getRepository(ChangeProposal::class)->findBy(['status' => ChangeProposalStatus::Pending, 'targetType' => self::ITEM_TYPE], [
            'id' => 'ASC',
        ]);

        $rows = [];
        foreach ($proposals as $proposal) {
            $isScopedEntry = in_array($proposal->getTargetId(), $entryIds, true);
            if (!$isScopedEntry || !$scope->grants($proposal->getProposedBy(), DataCategory::Interactions)) {
                continue;
            }

            $changes = [];
            foreach ($proposal->getChanges() as $change) {
                $changes[$change->field] = $change->toArray();
            }

            $rows[] = [
                'ref' => $proposal->getId(),
                'target_ref' => $proposal->getTargetId(),
                'changes' => $changes,
                'email' => $proposal->getProposedBy()->getEmail(),
                'created_at' => $proposal->getCreatedAt()->format(DateTimeInterface::ATOM),
            ];
        }

        return $rows;
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function importCard(array $row, ImportContext $context): void
    {
        $userId = $context->resolveRef(User::class, $row['email'] ?? null)?->getId();
        $entryId = $context->resolveItem(self::ITEM_TYPE, $row['glossary_ref'] ?? null);
        $direction = Direction::tryFrom((string) ($row['direction'] ?? ''));
        if ($userId === null || $entryId === null || $direction === null) {
            $context->count(self::KIND_CARDS, Outcome::Dropped);
            return;
        }

        $cardRepository = $this->em->getRepository(TrainerCard::class);
        if ($cardRepository->findOneBy(['userId' => $userId, 'glossary' => $entryId, 'direction' => $direction]) !== null) {
            $context->count(self::KIND_CARDS, Outcome::Matched);
            return;
        }

        $card = new TrainerCard(
            $userId,
            $this->em->getReference(Glossary::class, $entryId),
            $direction,
            $this->date($row['created_at'] ?? null) ?? new DateTimeImmutable(),
        );
        $card->setState(CardState::tryFrom((string) ($row['state'] ?? '')) ?? CardState::New);
        $card->setDueAt($this->date($row['due_at'] ?? null));
        $card->setIntervalDays((int) ($row['interval_days'] ?? 0));
        $card->setEasePermille((int) ($row['ease_permille'] ?? TrainerCard::DEFAULT_EASE));
        $card->setRepetitions((int) ($row['repetitions'] ?? 0));
        $card->setLapses((int) ($row['lapses'] ?? 0));
        $card->setMarked((bool) ($row['marked'] ?? false));
        $card->setSuspended((bool) ($row['suspended'] ?? false));

        $timesSeen = (int) ($row['times_seen'] ?? 0);
        $timesCorrect = (int) ($row['times_correct'] ?? 0);
        $lastReviewedAt = $this->date($row['last_reviewed_at'] ?? null) ?? $card->getCreatedAt();
        for ($answer = 0; $answer < $timesSeen; ++$answer) {
            $card->recordAnswer($answer < $timesCorrect, $lastReviewedAt);
        }

        $this->em->persist($card);
        $context->count(self::KIND_CARDS, Outcome::Created);
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function importDay(array $row, ImportContext $context): void
    {
        $userId = $context->resolveRef(User::class, $row['email'] ?? null)?->getId();
        $date = $this->date($row['day'] ?? null);
        if ($userId === null || $date === null) {
            $context->count(self::KIND_DAYS, Outcome::Dropped);
            return;
        }

        $day = new DateTimeImmutable($date->format('Y-m-d'));
        $dayRepository = $this->em->getRepository(TrainerDay::class);
        if ($dayRepository->findOneBy(['userId' => $userId, 'day' => $day]) !== null) {
            $context->count(self::KIND_DAYS, Outcome::Matched);
            return;
        }

        $trainerDay = new TrainerDay($userId, $day);
        $reviewed = (int) ($row['reviewed'] ?? 0);
        $correct = (int) ($row['correct'] ?? 0);
        $newStarted = (int) ($row['new_started'] ?? 0);
        for ($review = 0; $review < $reviewed; ++$review) {
            $trainerDay->record($review < $correct, $review < $newStarted);
        }

        $this->em->persist($trainerDay);
        $context->count(self::KIND_DAYS, Outcome::Created);
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function importProposal(array $row, ImportContext $context): void
    {
        $entryId = $context->resolveItem(self::ITEM_TYPE, $row['target_ref'] ?? null);
        $proposer = $context->resolveRef(User::class, $row['email'] ?? null);
        $changes = $this->readChanges($row['changes'] ?? null, $context);
        if ($entryId === null || !$proposer instanceof User || $changes === null) {
            $context->count(self::KIND_CHANGE_PROPOSALS, Outcome::Dropped);
            return;
        }

        $proposal = new ChangeProposal();
        $proposal->setTargetType(self::ITEM_TYPE);
        $proposal->setTargetId($entryId);
        $proposal->setProposedBy($proposer);
        $proposal->setStatus(ChangeProposalStatus::Pending);
        $proposal->setChanges($changes);
        $proposal->setCreatedAt($this->date($row['created_at'] ?? null) ?? new DateTimeImmutable());

        $this->em->persist($proposal);
        $context->mapRef(ChangeProposal::class, (int) ($row['ref'] ?? 0), $proposal);
        $context->count(self::KIND_CHANGE_PROPOSALS, Outcome::Created);
    }

    /**
     * @return list<FieldChange>|null
     */
    private function readChanges(mixed $changes, ImportContext $context): ?array
    {
        if (!is_array($changes) || $changes === []) {
            return null;
        }

        $fieldChanges = [];
        foreach ($changes as $field => $change) {
            if (!is_array($change)) {
                continue;
            }

            $before = $this->readValue($change['before'] ?? null);
            $after = $this->readValue($change['after'] ?? null);
            if ($field === GlossaryChangeTarget::FIELD_TAG) {
                $rekeyedBefore = $this->rekeyTags($before, $context);
                $rekeyedAfter = $this->rekeyTags($after, $context);
                if ($rekeyedBefore === false || $rekeyedAfter === false) {
                    return null;
                }

                $before = $rekeyedBefore;
                $after = $rekeyedAfter;
            }

            $resolution = $change['resolution'] ?? null;
            $fieldChanges[] = new FieldChange((string) $field, $before, $after, is_string($resolution) ? FieldResolution::tryFrom($resolution) : null);
        }

        return $fieldChanges === [] ? null : $fieldChanges;
    }

    private function rekeyTags(?string $value, ImportContext $context): string|false|null
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $tagIds = [];
        foreach ($this->glossaryService->decodeTagIds($value) as $archivedTagId) {
            $tagId = $context->resolveRef(ItemTag::class, $archivedTagId)?->getId();
            if ($tagId === null) {
                return false;
            }

            $tagIds[] = $tagId;
        }

        return $this->glossaryService->encodeTagIds($tagIds);
    }

    /**
     * @param list<int> $userIds
     * @return array<int, string>
     */
    private function emailsById(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $emails = [];
        foreach ($this->userRepository->findBy(['id' => array_values(array_unique($userIds))]) as $user) {
            $emails[(int) $user->getId()] = (string) $user->getEmail();
        }

        return $emails;
    }

    /**
     * @param array<array-key, mixed> $rows
     * @return list<array<array-key, mixed>>
     */
    private function rowsOf(array $rows, string $key): array
    {
        return array_values(array_filter(is_array($rows[$key] ?? null) ? $rows[$key] : [], is_array(...)));
    }

    private function readValue(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value) ?: null;
    }
}
