<?php declare(strict_types=1);

namespace Module\Circulation\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows who may join a waiting list. Verdicts compose with AND - any refusal blocks;
 * null is no opinion. A refusal carries the translation key that explains it.
 */
#[AutoconfigureTag]
interface EligibilityProviderInterface
{
    public function canRequest(string $context, string $itemType, int $itemId, int $userId): ?EligibilityVerdict;
}
