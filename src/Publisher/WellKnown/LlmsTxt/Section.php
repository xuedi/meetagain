<?php declare(strict_types=1);

namespace App\Publisher\WellKnown\LlmsTxt;

final readonly class Section
{
    /**
     * @param list<Link> $links
     */
    public function __construct(
        public string $heading,
        public array $links,
    ) {}
}
