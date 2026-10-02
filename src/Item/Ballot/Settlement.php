<?php declare(strict_types=1);

namespace App\Item\Ballot;

use App\Item\AssociationService;
use Module\Ballot\Contract\BallotOutcome;
use Module\Ballot\Contract\SettlementListenerInterface;
use Override;

final readonly class Settlement implements SettlementListenerInterface
{
    public function __construct(
        private AssociationService $associations,
        private Purpose $purpose,
    ) {}

    #[Override]
    public function getPriority(): int
    {
        return 0;
    }

    #[Override]
    public function supports(string $purpose): bool
    {
        return $this->purpose->itemTypeOf($purpose) !== null;
    }

    #[Override]
    public function settled(BallotOutcome $outcome): void
    {
        $itemType = $this->purpose->itemTypeOf($outcome->purpose);
        $subject = $outcome->subject;
        $winner = $outcome->winningKey;

        if ($itemType === null || $subject === null || $winner === null || !ctype_digit($winner)) {
            return;
        }

        $this->associations->attach($subject->id, $itemType, (int) $winner, (int) $outcome->openedByUserId);
    }
}
