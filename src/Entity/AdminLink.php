<?php declare(strict_types=1);

namespace App\Entity;

/**
 * @deprecated since 2026-04-30, use {@see \App\Admin\Navigation\AdminLink} instead.
 *             This class will be removed once all admin controllers have migrated to the new
 *             Admin\Navigation module. See plan 2026-04-30_admin-top-component.md.
 */
readonly class AdminLink
{
    public function __construct(
        private string $label,
        private string $route,
        private ?string $active = null,
        private ?string $role = null,
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

    public function getRole(): ?string
    {
        return $this->role;
    }
}
