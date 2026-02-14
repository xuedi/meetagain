<?php declare(strict_types=1);

namespace App\Entity;

readonly class Link
{
    public function __construct(
        private string $slug,
        private string $name,
        private int $priority = 0,
    ) {}

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}
