<?php declare(strict_types=1);

namespace App\Review;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Registers one entity type as able to receive proposed changes. The registry keys providers by
 * getTargetType() and shows only those whose owning plugin is active. Implementations own all
 * interpretation of field names and values: display labels, validation, and applying an approved
 * value onto the target.
 */
#[AutoconfigureTag]
interface ChangeTargetProviderInterface
{
    /** Directory key of the owning plugin, matched against the active-plugin list. */
    public function getPluginKey(): string;

    /** Registry key for this target type; the value stored in ChangeProposal::targetType. */
    public function getTargetType(): string;

    /** Display name of the target; null when the target no longer exists. */
    public function getTargetLabel(int $targetId): ?string;

    /** Context link for the review UI; null when the target has no page. */
    public function getTargetUrl(int $targetId): ?string;

    /** Pre-translated display label for a field key. */
    public function getFieldLabel(string $field): string;

    /** Display form of a stored value, e.g. resolving an id to its label. */
    public function formatValue(string $field, ?string $value): string;

    public function canPropose(User $user, int $targetId): bool;

    public function canReview(User $user, int $targetId): bool;

    /** Pre-translated error when the value cannot be applied to the target; null when valid. */
    public function validate(int $targetId, string $field, ?string $value): ?string;

    /** Writes the value onto the target. Only called after validate() returned null. */
    public function apply(int $targetId, string $field, ?string $value): void;
}
