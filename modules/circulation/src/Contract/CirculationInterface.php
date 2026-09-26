<?php declare(strict_types=1);

namespace Module\Circulation\Contract;

/**
 * What code outside circulation may ask of it. Every answer is a value object or a scalar, never an entity.
 */
interface CirculationInterface
{
    public const string COMMENT_TARGET = 'circulation_handover';

    public function contextFor(string $itemType): string;

    /**
     * Every context the ledger has ever recorded, with its item type.
     *
     * @return array<string, string> context => item type
     */
    public function ledgerContexts(): array;

    /**
     * @param list<string> $contexts
     */
    public function export(array $contexts): PortableShelf;

    /**
     * Writes the rows exactly as given, without matching queues, logging activity or notifying anyone.
     * Rows point at each other by ref: export hands out row ids, restore takes any number unique per row
     * kind. A ref the shelf does not carry resolves to null; a handover whose copy is missing is skipped.
     *
     * @return array<int, int> handover ref => restored handover id
     */
    public function restore(PortableShelf $shelf): array;
}
