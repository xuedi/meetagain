<?php declare(strict_types=1);

namespace App\Admin\Navigation;

final readonly class AdminLink
{
    public function __construct(
        public string $label,
        public string $route,
        public ?string $active = null,
        public ?string $role = null,
    ) {}

    // Temporary backward-compat getters: AdminNavigationService accepts both
    // this class and the deprecated App\Entity\AdminLink (private fields +
    // getter-only). Same getter shape on both lets the service stay agnostic
    // during the in-flight namespace migration. Once App\Entity\AdminLink
    // and AbstractAdminController are removed, drop these and read the
    // public readonly properties directly.

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

    public function getRole(): ?string
    {
        return $this->role;
    }
}
