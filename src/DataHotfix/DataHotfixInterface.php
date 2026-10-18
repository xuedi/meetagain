<?php declare(strict_types=1);

namespace App\DataHotfix;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * One-off data repair an admin runs once per database lifetime, locked via AppState.
 * Implementations are responsible for their own batching and flushing.
 */
#[AutoconfigureTag]
interface DataHotfixInterface
{
    /**
     * Stable globally-unique identifier. Convention: date-prefixed snake_case,
     * e.g. "2026_04_30_default_following_updates_off". Used as the AppState key suffix.
     */
    public function getIdentifier(): string;

    /**
     * Apply the data fix. Throwing aborts the run; the AppState lock is NOT written,
     * so the hotfix stays pending.
     */
    public function execute(): void;
}
