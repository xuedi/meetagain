<?php declare(strict_types=1);

namespace App\Circulation;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Mints the opaque scope string every circulation row is filed under. The first
 * non-null answer wins; the string is never parsed by the circulation code itself.
 */
#[AutoconfigureTag]
interface ContextProviderInterface
{
    public function getContext(string $itemType): ?string;

    /** Higher runs first. */
    public function getPriority(): int;
}
