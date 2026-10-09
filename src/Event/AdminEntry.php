<?php declare(strict_types=1);

namespace App\Event;

final readonly class AdminEntry
{
    /**
     * @param array<string, string> $fields
     */
    public function __construct(
        public string $action,
        public array $fields,
    ) {}
}
