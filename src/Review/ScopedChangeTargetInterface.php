<?php declare(strict_types=1);

namespace App\Review;

interface ScopedChangeTargetInterface
{
    /** @return array{key: string, label: string}|null */
    public function getApplyScope(int $targetId): ?array;

    /** Writes the value onto every further row the scope covers; the target itself is written by apply(). */
    public function applyToScope(int $targetId, string $scope, string $field, ?string $value): void;
}
