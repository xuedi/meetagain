<?php declare(strict_types=1);

namespace App\Metrics;

final readonly class Point
{
    /**
     * @param array<string, int|float> $fields
     * @param array<string, string> $tags
     */
    public function __construct(
        public string $measurement,
        public array $fields,
        public array $tags = [],
    ) {}
}
