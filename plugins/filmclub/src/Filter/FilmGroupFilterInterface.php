<?php declare(strict_types=1);

namespace Plugin\Filmclub\Filter;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Restricts filmclub entity visibility to the current group context.
 * Multiple implementations compose with AND logic.
 *
 * Conventions:
 *   null      = no opinion; bypasses filtering (no filter implementation registered)
 *   []        = block all; repository MUST return empty result, not omit the clause
 *   int[]     = restrict to these IDs
 */
#[AutoconfigureTag]
interface FilmGroupFilterInterface
{
    /** @return int[]|null */
    public function getAllowedFilmIds(): ?array;

    /** @return int[]|null */
    public function getAllowedSuggestionIds(): ?array;

    /** @return int[]|null */
    public function getAllowedEventIds(): ?array;

    /** @return int[]|null */
    public function getAllowedPollIds(): ?array;

    /** @return int[]|null */
    public function getAllowedNoteIds(): ?array;

    /** @return int[]|null */
    public function getAllowedWishlistEntryIds(): ?array;

    /** @return int[]|null */
    public function getAllowedSettingsIds(): ?array;
}
