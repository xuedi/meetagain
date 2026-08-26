<?php declare(strict_types=1);

namespace App\Circulation\Trust;

use App\Circulation\EligibilityProviderInterface;
use App\Circulation\EligibilityVerdict;
use App\Entity\User;
use Module\Trust\Contract\TrustInterface;
use Override;

final readonly class ParticipationEligibilityProvider implements EligibilityProviderInterface
{
    public function __construct(
        private ContextIndex $index,
        private TrustInterface $trust,
    ) {}

    #[Override]
    public function canRequest(string $context, string $itemType, int $itemId, User $user): ?EligibilityVerdict
    {
        if ($this->index->itemTypeFor($context) === null) {
            return null;
        }

        $userId = (int) $user->getId();
        if ($this->trust->meetsMinimum($context, $userId)) {
            return null;
        }

        return EligibilityVerdict::refused('circulation.flash_trust_minimum', [
            '%required%' => $this->trust->getConfig($context)->minimumToParticipate,
            '%current%' => $this->trust->getScore($context, $userId),
        ]);
    }
}
