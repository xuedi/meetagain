<?php declare(strict_types=1);

namespace App\Filter\Language;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for language filters.
 * Plugins can implement this to restrict which languages appear in the navbar.
 *
 * Multiple filters can be registered - they are composed with AND logic.
 * If any filter restricts a language, it will be hidden from the navbar.
 */
#[AutoconfigureTag]
interface LanguageFilterInterface
{
    /**
     * Get priority for filter ordering.
     * Higher priority filters are applied first.
     * Default: 0
     */
    public function getPriority(): int;

    /**
     * Get the allowed language codes for the current context.
     *
     * @return array<string>|null Returns:
     *         - null: No filtering (allow all languages)
     *         - array<string>: Only these language codes are allowed
     *         - []: No languages allowed (empty result)
     */
    public function getLanguageCodeFilter(): ?array;

    /**
     * Check if a specific language code is accessible in the current context.
     *
     * @param string $code The language code to check
     * @return bool|null Returns:
     *         - null: No opinion (let other filters decide)
     *         - true: Explicitly allow this language
     *         - false: Explicitly deny this language
     */
    public function isLanguageAccessible(string $code): ?bool;
}
