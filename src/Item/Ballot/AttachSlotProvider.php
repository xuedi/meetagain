<?php declare(strict_types=1);

namespace App\Item\Ballot;

use App\Item\AttachSlot;
use App\Item\AttachSlotProviderInterface;
use Module\Ballot\Contract\BallotInterface;
use Module\Ballot\Contract\BallotSubject;
use Module\Ballot\Contract\BallotView;
use Override;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class AttachSlotProvider implements AttachSlotProviderInterface
{
    public function __construct(
        private BallotInterface $ballots,
        private UrlGeneratorInterface $urlGenerator,
        private Purpose $purpose,
    ) {}

    #[Override]
    public function getAttachSlots(int $eventId, string $itemType): array
    {
        $running = $this->runningBallot($eventId, $itemType);
        if ($running instanceof BallotView) {
            return [new AttachSlot(
                url: $this->urlGenerator->generate('app_item_ballot_show', ['id' => $running->id]),
                labelKey: 'item_ballot.slot_open',
                icon: 'check-to-slot',
            )];
        }

        return [new AttachSlot(
            url: $this->urlGenerator->generate('app_item_ballot_create', ['eventId' => $eventId, 'itemType' => $itemType]),
            labelKey: 'item_ballot.slot_create',
            icon: 'check-to-slot',
        )];
    }

    private function runningBallot(int $eventId, string $itemType): ?BallotView
    {
        $purpose = $this->purpose->forType($itemType);
        foreach ($this->ballots->listForSubject(new BallotSubject(Purpose::SUBJECT_TYPE, $eventId), null) as $view) {
            if ($view->purpose === $purpose && !$view->status->isResolved()) {
                return $view;
            }
        }

        return null;
    }
}
