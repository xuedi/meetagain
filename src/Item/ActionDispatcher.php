<?php declare(strict_types=1);

namespace App\Item;

use App\Enum\ItemAction;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class ActionDispatcher
{
    /**
     * @param iterable<ActionInterface> $handlers
     */
    public function __construct(
        #[AutowireIterator(ActionInterface::class)]
        private iterable $handlers,
    ) {}

    public function dispatch(ItemAction $action, string $itemType, int $itemId): void
    {
        foreach ($this->handlers as $handler) {
            $handler->onItemAction($action, $itemType, $itemId);
        }
    }
}
