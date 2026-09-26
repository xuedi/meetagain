<?php declare(strict_types=1);

namespace Module\Circulation\Internal\Trust;

use Module\Circulation\Contract\EligibilityProviderInterface;
use Module\Circulation\Contract\EligibilityVerdict;
use Module\Trust\Contract\TrustInterface;
use Override;

final readonly class ParticipationEligibilityProvider implements EligibilityProviderInterface
{
    public function __construct(
        private ContextIndex $index,
        private TrustInterface $trust,
    ) {}

    #[Override]
    public function canRequest(string $context, string $itemType, int $itemId, int $userId): ?EligibilityVerdict
    {
        if ($this->index->itemTypeFor($context) === null) {
            return null;
        }

        if ($this->trust->meetsMinimum($context, $userId)) {
            return null;
        }

        return EligibilityVerdict::refused('circulation.flash_trust_minimum', [
            '%required%' => $this->trust->getConfig($context)->minimumToParticipate,
            '%current%' => $this->trust->getScore($context, $userId),
        ]);
    }
}
