<?php declare(strict_types=1);

namespace Plugin\Boardgames\Portability;

use App\Entity\Event;
use App\Entity\User;
use App\Portability\DataCategory;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\PluginSectionInterface;
use App\Portability\Scope;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Plugin\Boardgames\Entity\BringRequest;
use Plugin\Boardgames\Entity\Game;
use Plugin\Boardgames\Entity\GameOwnership;
use Plugin\Boardgames\Entity\GamePledge;
use Plugin\Boardgames\Enum\CopyCondition;
use Plugin\Boardgames\Enum\PledgeStatus;
use Plugin\Boardgames\Enum\RequestStatus;
use Plugin\Boardgames\Service\GameService;

readonly class ShelfSection implements PluginSectionInterface
{
    public const string KIND_OWNERSHIPS = 'boardgames_ownerships';
    public const string KIND_PLEDGES = 'boardgames_pledges';
    public const string KIND_REQUESTS = 'boardgames_requests';

    private const array BLOCK_KINDS = [
        'ownerships' => self::KIND_OWNERSHIPS,
        'pledges' => self::KIND_PLEDGES,
        'requests' => self::KIND_REQUESTS,
    ];

    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'boardgames';
    }

    #[Override]
    public function getOrder(): int
    {
        return 100;
    }

    #[Override]
    public function getPluginKey(): string
    {
        return 'boardgames';
    }

    #[Override]
    public function getKindLabels(): array
    {
        return [
            self::KIND_OWNERSHIPS => 'boardgames_portability.kind_ownerships',
            self::KIND_PLEDGES => 'boardgames_portability.kind_pledges',
            self::KIND_REQUESTS => 'boardgames_portability.kind_requests',
        ];
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        $gameIds = $scope->itemIds[GameService::ITEM_TYPE] ?? [];
        if ($gameIds === []) {
            return [];
        }

        $ownerships = $this->ownershipRows($scope, $gameIds);
        $pledges = $scope->eventIds === [] ? [] : $this->pledgeRows($scope, $gameIds);
        $requests = $scope->eventIds === [] ? [] : $this->requestRows($scope, $gameIds);
        if ($ownerships === [] && $pledges === [] && $requests === []) {
            return [];
        }

        return ['ownerships' => $ownerships, 'pledges' => $pledges, 'requests' => $requests];
    }

    #[Override]
    public function import(array $rows, ImportContext $context): void
    {
        if (!$context->knowsItemType(GameService::ITEM_TYPE)) {
            foreach (self::BLOCK_KINDS as $block => $kind) {
                $context->count($kind, Outcome::Skipped, count($this->rowsOf($rows, $block)));
            }

            return;
        }

        foreach ($this->rowsOf($rows, 'ownerships') as $row) {
            $this->importOwnership($row, $context);
        }

        foreach ($this->rowsOf($rows, 'pledges') as $row) {
            $this->importPledge($row, $context);
        }

        foreach ($this->rowsOf($rows, 'requests') as $row) {
            $this->importRequest($row, $context);
        }
    }

    /**
     * @param list<int> $gameIds
     * @return list<array<string, mixed>>
     */
    private function ownershipRows(Scope $scope, array $gameIds): array
    {
        $rows = [];
        foreach ($this->em->getRepository(GameOwnership::class)->findBy(['game' => $gameIds], ['id' => 'ASC']) as $ownership) {
            $user = $ownership->getUser();
            if (!$scope->grants($user, DataCategory::Collections)) {
                continue;
            }

            $rows[] = [
                'game_ref' => $ownership->getGame()?->getId(),
                'email' => $user?->getEmail(),
                'copy_language' => $ownership->getCopyLanguage(),
                'copy_condition' => $ownership->getCopyCondition()?->value,
                'notes' => $ownership->getNotes(),
                'can_teach' => $ownership->isCanTeach(),
                'willing_to_bring' => $ownership->isWillingToBring(),
                'is_public' => $ownership->isPublic(),
                'acquired_at' => $ownership->getAcquiredAt()?->format('Y-m-d'),
                'created_at' => $ownership->getCreatedAt()?->format(DateTimeInterface::ATOM),
            ];
        }

        return $rows;
    }

    /**
     * @param list<int> $gameIds
     * @return list<array<string, mixed>>
     */
    private function pledgeRows(Scope $scope, array $gameIds): array
    {
        $rows = [];
        foreach ($this->em->getRepository(GamePledge::class)->findBy(['event' => $scope->eventIds, 'game' => $gameIds], ['id' => 'ASC']) as $pledge) {
            $user = $pledge->getUser();
            if (!$scope->grants($user, DataCategory::Collections)) {
                continue;
            }

            $rows[] = [
                'event_ref' => $pledge->getEvent()?->getId(),
                'game_ref' => $pledge->getGame()?->getId(),
                'email' => $user?->getEmail(),
                'status' => $pledge->getStatus()->value,
                'created_at' => $pledge->getCreatedAt()?->format(DateTimeInterface::ATOM),
            ];
        }

        return $rows;
    }

    /**
     * @param list<int> $gameIds
     * @return list<array<string, mixed>>
     */
    private function requestRows(Scope $scope, array $gameIds): array
    {
        $rows = [];
        foreach ($this->em->getRepository(BringRequest::class)->findBy(['event' => $scope->eventIds, 'game' => $gameIds], ['id' => 'ASC']) as $request) {
            $requester = $request->getRequestedBy();
            $owner = $request->getOwnerUser();
            if (!$scope->grants($requester, DataCategory::Collections) || !$scope->grants($owner, DataCategory::Collections)) {
                continue;
            }

            $rows[] = [
                'event_ref' => $request->getEvent()?->getId(),
                'game_ref' => $request->getGame()?->getId(),
                'requested_by_email' => $requester?->getEmail(),
                'owner_email' => $owner?->getEmail(),
                'status' => $request->getStatus()->value,
                'message' => $request->getMessage(),
                'created_at' => $request->getCreatedAt()?->format(DateTimeInterface::ATOM),
                'responded_at' => $request->getRespondedAt()?->format(DateTimeInterface::ATOM),
            ];
        }

        return $rows;
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function importOwnership(array $row, ImportContext $context): void
    {
        $game = $this->resolveGame($row['game_ref'] ?? null, $context);
        $user = $context->resolveRef(User::class, $row['email'] ?? null);
        if (!$game instanceof Game || !$user instanceof User) {
            $context->count(self::KIND_OWNERSHIPS, Outcome::Dropped);

            return;
        }

        if ($this->em->getRepository(GameOwnership::class)->findOneBy(['user' => $user, 'game' => $game]) !== null) {
            $context->count(self::KIND_OWNERSHIPS, Outcome::Matched);

            return;
        }

        $ownership = new GameOwnership()
            ->setUser($user)
            ->setGame($game)
            ->setCopyLanguage($this->text($row['copy_language'] ?? null))
            ->setCopyCondition(CopyCondition::tryFrom((string) ($row['copy_condition'] ?? '')))
            ->setNotes($this->text($row['notes'] ?? null))
            ->setCanTeach((bool) ($row['can_teach'] ?? false))
            ->setWillingToBring((bool) ($row['willing_to_bring'] ?? true))
            ->setPublic((bool) ($row['is_public'] ?? true))
            ->setAcquiredAt($this->day($row['acquired_at'] ?? null))
            ->setCreatedAt($this->timestamp($row['created_at'] ?? null) ?? new DateTimeImmutable());

        $this->em->persist($ownership);
        $context->count(self::KIND_OWNERSHIPS, Outcome::Created);
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function importPledge(array $row, ImportContext $context): void
    {
        $event = $context->resolveRef(Event::class, $row['event_ref'] ?? null);
        $game = $this->resolveGame($row['game_ref'] ?? null, $context);
        $user = $context->resolveRef(User::class, $row['email'] ?? null);
        if (!$event instanceof Event || !$game instanceof Game || !$user instanceof User) {
            $context->count(self::KIND_PLEDGES, Outcome::Dropped);

            return;
        }

        $pledge = new GamePledge()
            ->setEvent($event)
            ->setGame($game)
            ->setUser($user)
            ->setStatus(PledgeStatus::tryFrom((string) ($row['status'] ?? '')) ?? PledgeStatus::Pledged)
            ->setCreatedAt($this->timestamp($row['created_at'] ?? null) ?? new DateTimeImmutable());

        $this->em->persist($pledge);
        $context->count(self::KIND_PLEDGES, Outcome::Created);
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function importRequest(array $row, ImportContext $context): void
    {
        $event = $context->resolveRef(Event::class, $row['event_ref'] ?? null);
        $game = $this->resolveGame($row['game_ref'] ?? null, $context);
        $requester = $context->resolveRef(User::class, $row['requested_by_email'] ?? null);
        $owner = $context->resolveRef(User::class, $row['owner_email'] ?? null);
        $isComplete = $event instanceof Event && $game instanceof Game && $requester instanceof User && $owner instanceof User;
        if (!$isComplete) {
            $context->count(self::KIND_REQUESTS, Outcome::Dropped);

            return;
        }

        $request = new BringRequest()
            ->setEvent($event)
            ->setGame($game)
            ->setRequestedBy($requester)
            ->setOwnerUser($owner)
            ->setStatus(RequestStatus::tryFrom((string) ($row['status'] ?? '')) ?? RequestStatus::Open)
            ->setMessage($this->text($row['message'] ?? null))
            ->setCreatedAt($this->timestamp($row['created_at'] ?? null) ?? new DateTimeImmutable())
            ->setRespondedAt($this->timestamp($row['responded_at'] ?? null));

        $this->em->persist($request);
        $context->count(self::KIND_REQUESTS, Outcome::Created);
    }

    private function resolveGame(mixed $ref, ImportContext $context): ?Game
    {
        $gameId = $context->resolveItem(GameService::ITEM_TYPE, $ref);

        return $gameId === null ? null : $this->em->find(Game::class, $gameId);
    }

    /**
     * @param array<array-key, mixed> $rows
     * @return list<array<array-key, mixed>>
     */
    private function rowsOf(array $rows, string $block): array
    {
        $blockRows = $rows[$block] ?? [];

        return is_array($blockRows) ? array_values(array_filter($blockRows, is_array(...))) : [];
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function day(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) ?: null : null;
    }

    private function timestamp(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) ? DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value) ?: null : null;
    }
}
