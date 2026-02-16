<?php declare(strict_types=1);

namespace App\Filter\Language;

readonly class LanguageFilterResult
{
    /**
     * @param array<string>|null $languageCodes Restricted language codes, or null for no restriction
     * @param bool $hasActiveFilter Whether any filter is actively restricting
     */
    public function __construct(
        private ?array $languageCodes,
        private bool $hasActiveFilter,
    ) {}

    /** @return array<string>|null */
    public function getLanguageCodes(): ?array
    {
        return $this->languageCodes;
    }

    public function hasActiveFilter(): bool
    {
        return $this->hasActiveFilter;
    }

    public function isEmpty(): bool
    {
        return $this->languageCodes === [];
    }

    public static function noFilter(): self
    {
        return new self(null, false);
    }

    public static function emptyResult(): self
    {
        return new self([], true);
    }
}
