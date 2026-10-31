<?php declare(strict_types=1);

namespace Module\Proposal\Contract\Change;

interface ChangeInterface
{
    public const string BALLOT_PURPOSE = 'contribution.field';

    /**
     * Drops unchanged fields; null when nothing was left to propose, otherwise the new proposal id.
     *
     * @param list<FieldChange> $changes
     */
    public function propose(string $targetType, int $targetId, int $proposerId, array $changes): ?int;

    /** @return list<View> */
    public function pendingFor(string $targetType, int $targetId): array;

    /** @return list<int> */
    public function pendingTargetIds(string $targetType): array;

    public function countPending(string $targetType, ?int $targetId = null): int;

    public function find(int $id): ?View;

    public function removeForTarget(string $targetType, int $targetId): void;

    /**
     * @param list<string> $targetTypes
     * @return list<View>
     */
    public function exportPending(array $targetTypes): array;

    /** Stores a pending proposal as given, without permission checks, validation or activity. */
    public function restore(PortableChange $change): int;
}
