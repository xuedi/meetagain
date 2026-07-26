<?php declare(strict_types=1);

namespace App\Service\Item;

use App\Item\AttachControl;
use App\Item\AttachControlType;
use App\Item\AttachSlot;
use App\Item\AttachSlotProviderInterface;
use App\Item\TypeRegistry;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class AttachControlBuilder
{
    /**
     * @param iterable<AttachSlotProviderInterface> $slotProviders
     */
    public function __construct(
        private TypeRegistry $registry,
        #[AutowireIterator(AttachSlotProviderInterface::class)]
        private iterable $slotProviders,
    ) {}

    public function build(int $eventId): AttachControl
    {
        $types = [];
        foreach ($this->registry->all() as $provider) {
            $itemType = $provider->getKey();
            $types[] = new AttachControlType(
                $itemType,
                $provider->getLabelKey(),
                $provider->renderAttachPicker($eventId),
                $this->collectSlots($eventId, $itemType),
            );
        }

        return new AttachControl($eventId, $types);
    }

    /**
     * @return list<AttachSlot>
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
