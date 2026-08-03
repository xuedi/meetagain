<?php declare(strict_types=1);

namespace App\Publisher\WellKnown\LlmsTxt;

final readonly class Link
{
    public function __construct(
        public string $label,
        public string $url,
        public ?string $note = null,
    ) {}
}
