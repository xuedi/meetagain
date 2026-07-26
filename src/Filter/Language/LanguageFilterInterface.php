<?php declare(strict_types=1);

namespace App\Filter\Language;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the languages offered in the navbar selector. Implementations compose with AND-intersection.
 */
#[AutoconfigureTag]
interface LanguageFilterInterface
{
    /**
     * Higher priority runs first. Default: 0.
     */
    public function getPriority(): int;

    /**
     * @return array<string>|null null = no opinion, [] = block all, [id, ...] = allow-list
     */
    public function getLanguageCodeFilter(): ?array;

    /**
     * @return bool|null null = no opinion, true = allow, false = deny
     */
    public function isLanguageAccessible(string $code): ?bool;
}
