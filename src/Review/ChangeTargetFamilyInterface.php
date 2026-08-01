<?php declare(strict_types=1);

namespace App\Review;

/**
 * A provider that serves a whole family of target types rather than the single one it registers
 * under. The registry falls back to the family only when no provider matches the target type
 * exactly, and asks it for an instance bound to that target type.
 */
interface ChangeTargetFamilyInterface
{
    public function handlesTargetType(string $targetType): bool;

    public function forTargetType(string $targetType): ChangeTargetProviderInterface;
}
