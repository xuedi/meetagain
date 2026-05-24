<?php declare(strict_types=1);

namespace Plugin\Ranking\Service;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Resolves the active group ID for ranking reads and writes. Provider chain:
 * the first non-null result wins. Returning null means "no opinion" - the
 * resolver falls back to the next provider, or finally to the single-group
 * default when no provider has an opinion.
 */
#[AutoconfigureTag]
interface GroupContextProviderInterface
{
    public function getCurrentGroupId(): ?int;

    /** Human-readable group name used by the danger-zone confirm step. Null = no opinion. */
    public function getCurrentGroupName(): ?string;

    /** Higher runs first. */
    public function getPriority(): int;
}
