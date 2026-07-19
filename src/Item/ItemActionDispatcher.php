<?php declare(strict_types=1);

namespace App\Item;

use App\Enum\ItemAction;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Dispatches item lifecycle actions to all registered handlers.
 * Mirrors EntityActionDispatcher; called by item plugins after flush.
 */
readonly class ItemActionDispatcher
{
    /**
     * @param iterable<ItemActionInterface> $handlers
     */
    public function __construct(
        #[AutowireIterator(ItemActionInterface::class)]
        private iterable $handlers,
    ) {}

    public function dispatch(ItemAction $action, string $itemType, int $itemId): void
    {
        foreach ($this->handlers as $handler) {
            $handler->onItemAction($action, $itemType, $itemId);
        }
    }
}
