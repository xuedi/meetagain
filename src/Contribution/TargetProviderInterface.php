<?php declare(strict_types=1);

namespace App\Contribution;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Registers one kind of row as something a member may help correct. The registry keys providers by
 * getType(); core never learns what a type means.
 */
#[AutoconfigureTag]
interface TargetProviderInterface
{
    /** Registry key for this kind of row, and the `{type}` segment in the correction routes. */
    public function getType(): string;

    /** Plugin that owns this kind of row, or the empty string for core. */
    public function getPluginKey(): string;

    /**
     * The per-row rule the correction form and every inbound link share. Implementations answer the
     * union of every route that can reach the row, never one route's rule alone.
     */
    public function mayTouch(User $user, int|string $id): bool;
}
