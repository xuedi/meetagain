<?php declare(strict_types=1);

namespace Plugin\Voting\Item;

use App\Item\AttachSlot;
use App\Item\AttachSlotProviderInterface;
use Override;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class AttachSlotProvider implements AttachSlotProviderInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Override]
    public function getAttachSlots(int $eventId, string $itemType): array
    {
        return [
            new AttachSlot(
                url: $this->urlGenerator->generate('app_voting_poll_create', ['eventId' => $eventId, 'itemType' => $itemType]),
                labelKey: 'voting_attach.put_to_vote',
                icon: 'check-to-slot',
            ),
        ];
    }
}
