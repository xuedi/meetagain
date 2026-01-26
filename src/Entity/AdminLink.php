<?php declare(strict_types=1);

namespace App\Entity;

readonly class AdminLink
{
    public function __construct(
        private string $label,
        private string $route,
        private ?string $active = null,
    ) {}

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    public function getActive(): ?string
    {
        return $this->active;
    }
}
