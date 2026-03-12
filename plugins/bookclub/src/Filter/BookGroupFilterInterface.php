<?php declare(strict_types=1);

namespace Plugin\Bookclub\Filter;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for bookclub group scoping filters.
 * Plugins can implement this to restrict bookclub data to the current group context.
 *
 * Multiple filters can be registered - they are composed with AND logic.
 */
#[AutoconfigureTag]
interface BookGroupFilterInterface
{
    /**
     * Returns the allowed suggestion IDs for the current group, or null for no filtering.
     *
     * @return int[]|null
     */
    public function getAllowedSuggestionIds(): ?array;

    /**
     * Returns the allowed event IDs to scope bookclub polls, or null for no filtering.
     *
     * @return int[]|null
     */
    public function getAllowedEventIds(): ?array;

    /**
     * Returns the allowed book IDs for the current group, or null for no filtering.
     *
     * @return int[]|null
     */
    public function getAllowedBookIds(): ?array;
}
