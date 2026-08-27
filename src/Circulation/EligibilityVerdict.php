<?php declare(strict_types=1);

namespace App\Circulation;

final readonly class EligibilityVerdict
{
    /**
     * @param array<string, string|int> $reasonParams
     */
    private function __construct(
        public bool $allowed,
        public ?string $reasonKey = null,
        public array $reasonParams = [],
    ) {}

    public static function allowed(): self
    {
        return new self(true);
    }

    /**
     * @param array<string, string|int> $reasonParams
     */
    public static function refused(string $reasonKey, array $reasonParams = []): self
    {
        return new self(false, $reasonKey, $reasonParams);
    }
}
