<?php declare(strict_types=1);

namespace App\Service\Email;

final readonly class PreviewSweepResult
{
    /**
     * @param list<string> $identifiers
     * @param list<string> $locales
     * @param list<string> $withoutType
     * @param array<string, string> $errors recipient => reason
     */
    public function __construct(
        public int $enqueued,
        public array $identifiers,
        public array $locales,
        public array $withoutType,
        public array $errors,
        public bool $tagged,
    ) {}
}
