<?php declare(strict_types=1);

namespace Plugin\Glossary\Filter;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Restricts which glossary entries are visible in the current request. Multiple
 * implementations compose with AND logic.
 *
 * Conventions:
 *   null  = no opinion; bypasses filtering (no implementation registered)
 *   []    = block all; repository MUST return empty, not omit the clause
 *   int[] = restrict to these ids
 */
#[AutoconfigureTag]
interface GlossaryFilterInterface
{
    /** @return int[]|null */
    public function getAllowedGlossaryIds(): ?array;
}
