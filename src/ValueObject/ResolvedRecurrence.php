<?php declare(strict_types=1);

namespace App\ValueObject;

final readonly class ResolvedRecurrence
{
    public function __construct(
        public string $rfcString,
        public string $lookaheadModifier,
    ) {}
}
