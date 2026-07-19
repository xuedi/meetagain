<?php declare(strict_types=1);

namespace App\Service\Item;

use App\Item\ItemAttachControl;
use App\Item\ItemAttachControlType;
use App\Item\ItemAttachSlot;
use App\Item\ItemAttachSlotProviderInterface;
use App\Item\ItemTypeRegistry;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Assembles the event detail attach control from the active item types: each type's rendered
 * picker fragment plus the union of subsystem attach slots. Permission gating stays in the
 * controller; this only builds the view-model.
 */
readonly class ItemAttachControlBuilder
{
    /**
     * @param iterable<ItemAttachSlotProviderInterface> $slotProviders
     */
    public function __construct(
        private ItemTypeRegistry $registry,
        #[AutowireIterator(ItemAttachSlotProviderInterface::class)]
        private iterable $slotProviders,
    ) {}

    public function build(int $eventId): ItemAttachControl
    {
        $types = [];
        foreach ($this->registry->all() as $provider) {
            $itemType = $provider->getKey();
            $types[] = new ItemAttachControlType(
                $itemType,
                $provider->getLabelKey(),
                $provider->renderAttachPicker($eventId),
                $this->collectSlots($eventId, $itemType),
            );
        }

        return new ItemAttachControl($eventId, $types);
    }

    /**
     * @return list<ItemAttachSlot>
     */
    private function collectSlots(int $eventId, string $itemType): array
    {
        $slots = [];
        foreach ($this->slotProviders as $slotProvider) {
            foreach ($slotProvider->getAttachSlots($eventId, $itemType) as $slot) {
                $slots[] = $slot;
            }
        }

        return $slots;
    }
}
