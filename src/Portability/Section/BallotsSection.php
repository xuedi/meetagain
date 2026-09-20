<?php declare(strict_types=1);

namespace App\Portability\Section;

use App\Entity\User;
use App\Portability\Ballot\PurposeInterface;
use App\Portability\DataCategory;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\SectionInterface;
use App\Repository\UserRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Module\Ballot\Contract\BallotInterface;
use Module\Ballot\Contract\BallotStatus;
use Module\Ballot\Contract\BallotSubject;
use Module\Ballot\Contract\Candidate;
use Module\Ballot\Contract\PortableBallot;
use Module\Ballot\Contract\SettlementMode;
use Module\Ballot\Contract\TallyMode;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class BallotsSection implements SectionInterface
{
    /**
     * @param iterable<PurposeInterface> $purposes
     */
    public function __construct(
        private BallotInterface $ballots,
        private UserRepository $userRepository,
        #[AutowireIterator(PurposeInterface::class)]
        private iterable $purposes,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'ballots';
    }

    #[Override]
    public function getOrder(): int
    {
        return 110;
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        $ballots = array_values(array_filter(
            $this->ballots->exportAll(),
            fn(PortableBallot $ballot): bool => (
                $this->purposeFor($ballot->purpose)?->inScope($ballot->purpose, $ballot->subject, $this->keysOf($ballot), $scope) ?? false
            ),
        ));
        $users = $this->usersOf($ballots);

        return array_map(fn(PortableBallot $ballot): array => $this->row($ballot, $scope, $users), $ballots);
    }

    #[Override]
    public function import(array $rows, ImportContext $context): void
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $purposeKey = (string) ($row['purpose'] ?? '');
            $purpose = $this->purposeFor($purposeKey);
            if (!$purpose instanceof PurposeInterface) {
                $context->count($this->getKey(), Outcome::Skipped);
                continue;
            }

            $ballot = $this->restorable($row, $purposeKey, $purpose, $context);
            if (!$ballot instanceof PortableBallot) {
                $context->count($this->getKey(), Outcome::Dropped);
                continue;
            }

            $this->ballots->restore($ballot);
            $context->count($this->getKey(), Outcome::Created);
        }
    }

    /**
     * @param array<int, User> $users
     * @return array<string, mixed>
     */
    private function row(PortableBallot $ballot, Scope $scope, array $users): array
    {
        $votes = [];
        foreach ($ballot->votes as $userId => $keys) {
            $voter = $users[$userId] ?? null;
            if (!$voter instanceof User || !$scope->grants($voter, DataCategory::Interactions)) {
                continue;
            }

            sort($keys);
            $votes[] = ['email' => (string) $voter->getEmail(), 'keys' => $keys];
        }
        usort($votes, static fn(array $a, array $b): int => $a['email'] <=> $b['email']);

        $settledBy = $ballot->settledByUserId;

        return [
            'purpose' => $ballot->purpose,
            'title' => $ballot->title,
            'subject_type' => $ballot->subject?->type,
            'subject_ref' => $ballot->subject?->id,
            'status' => $ballot->status->value,
            'tally_mode' => $ballot->tallyMode->value,
            'settlement_mode' => $ballot->settlementMode->value,
            'deadline' => $ballot->deadline->format(DateTimeInterface::ATOM),
            'opened_by_email' => $scope->creditEmail($users[$ballot->openedByUserId] ?? null),
            'winning_key' => $ballot->winningKey,
            'tied_keys' => $ballot->tiedKeys,
            'settled_by_email' => $settledBy === null ? null : $scope->creditEmail($users[$settledBy] ?? null),
            'settled_at' => $ballot->settledAt?->format(DateTimeInterface::ATOM),
            'created_at' => $ballot->createdAt->format(DateTimeInterface::ATOM),
            'candidates' => array_map(static fn(Candidate $candidate): array => ['key' => $candidate->key, 'label' => $candidate->label], $ballot->candidates),
            'votes' => $votes,
        ];
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function restorable(array $row, string $purposeKey, PurposeInterface $purpose, ImportContext $context): ?PortableBallot
    {
        $subject = null;
        $subjectType = $row['subject_type'] ?? null;
        $subjectRef = $row['subject_ref'] ?? null;
        if ($subjectType !== null && $subjectRef !== null) {
            $subject = $purpose->importSubject(new BallotSubject((string) $subjectType, (int) $subjectRef), $context);
            if (!$subject instanceof BallotSubject) {
                return null;
            }
        }

        $keys = [];
        $candidates = [];
        foreach (is_array($row['candidates'] ?? null) ? $row['candidates'] : [] as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $key = (string) ($candidate['key'] ?? '');
            $importedKey = $purpose->importKey($purposeKey, $key, $context);
            if ($importedKey === null) {
                continue;
            }

            $keys[$key] = $importedKey;
            $candidates[] = new Candidate($importedKey, (string) ($candidate['label'] ?? ''));
        }

        $winningKey = isset($row['winning_key']) ? (string) $row['winning_key'] : null;
        $tiedKeys = array_map(strval(...), array_values(is_array($row['tied_keys'] ?? null) ? $row['tied_keys'] : []));
        $isWinnerLost = $winningKey !== null && !isset($keys[$winningKey]);
        $isTieLost = array_any($tiedKeys, static fn(string $key): bool => !isset($keys[$key]));
        if ($candidates === [] || $isWinnerLost || $isTieLost) {
            return null;
        }

        $settledByEmail = $row['settled_by_email'] ?? null;
        $settledAt = $row['settled_at'] ?? null;

        return new PortableBallot(
            purpose: $purposeKey,
            status: BallotStatus::tryFrom((string) ($row['status'] ?? '')) ?? BallotStatus::Open,
            tallyMode: TallyMode::tryFrom((string) ($row['tally_mode'] ?? '')) ?? TallyMode::Approval,
            settlementMode: SettlementMode::tryFrom((string) ($row['settlement_mode'] ?? '')) ?? SettlementMode::Automatic,
            deadline: $this->date($row['deadline'] ?? null),
            openedByUserId: $this->userId($row['opened_by_email'] ?? null, $context),
            createdAt: $this->date($row['created_at'] ?? null),
            candidates: $candidates,
            votes: $this->votes($row['votes'] ?? null, $keys, $context),
            title: isset($row['title']) ? (string) $row['title'] : null,
            subject: $subject,
            winningKey: $winningKey === null ? null : $keys[$winningKey],
            tiedKeys: array_map(static fn(string $key): string => $keys[$key], $tiedKeys),
            settledByUserId: $settledByEmail === null ? null : $this->userId($settledByEmail, $context),
            settledAt: $settledAt === null ? null : $this->date($settledAt),
        );
    }

    /**
     * @param array<array-key, string> $keys
     * @return array<int, list<string>>
     */
    private function votes(mixed $rows, array $keys, ImportContext $context): array
    {
        $votes = [];
        foreach (is_array($rows) ? $rows : [] as $vote) {
            if (!is_array($vote)) {
                continue;
            }

            $voter = $context->resolveRef(User::class, $vote['email'] ?? null);
            if (!$voter instanceof User) {
                continue;
            }

            $selection = [];
            foreach (is_array($vote['keys'] ?? null) ? $vote['keys'] : [] as $key) {
                $importedKey = $keys[(string) $key] ?? null;
                if ($importedKey !== null) {
                    $selection[] = $importedKey;
                }
            }

            if ($selection !== []) {
                $votes[(int) $voter->getId()] = $selection;
            }
        }

        return $votes;
    }

    /**
     * @param list<PortableBallot> $ballots
     * @return array<int, User>
     */
    private function usersOf(array $ballots): array
    {
        $userIds = [];
        foreach ($ballots as $ballot) {
            $userIds[$ballot->openedByUserId] = true;
            if ($ballot->settledByUserId !== null) {
                $userIds[$ballot->settledByUserId] = true;
            }

            foreach (array_keys($ballot->votes) as $voterId) {
                $userIds[$voterId] = true;
            }
        }

        if ($userIds === []) {
            return [];
        }

        $users = [];
        foreach ($this->userRepository->findBy(['id' => array_keys($userIds)]) as $user) {
            $users[(int) $user->getId()] = $user;
        }

        return $users;
    }

    /**
     * @return list<string>
     */
    private function keysOf(PortableBallot $ballot): array
    {
        return array_map(static fn(Candidate $candidate): string => $candidate->key, $ballot->candidates);
    }

    private function purposeFor(string $purpose): ?PurposeInterface
    {
        foreach ($this->purposes as $candidate) {
            if ($candidate->supports($purpose)) {
                return $candidate;
            }
        }

        return null;
    }

    private function userId(mixed $email, ImportContext $context): int
    {
        return (int) ($context->resolveRef(User::class, $email) ?? $context->getSystemUser())->getId();
    }

    private function date(mixed $value): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, (string) $value) ?: new DateTimeImmutable();
    }
}
