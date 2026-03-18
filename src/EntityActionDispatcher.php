<?php declare(strict_types=1);

namespace App;

use App\EntityActionInterface;
use App\Enum\EntityAction;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Dispatches entity lifecycle actions to all registered handlers.
 * Follows the same pattern as EventFilterService, CmsFilterService, etc.
 */
readonly class EntityActionDispatcher
{
    /**
     * @param iterable<EntityActionInterface> $handlers
     */
    public function __construct(
        #[AutowireIterator(EntityActionInterface::class)]
        private iterable $handlers,
    ) {}

    /**
     * Dispatch an action to all registered handlers.
     * Called by core controllers after entity operations complete.
     */
    public function dispatch(EntityAction $action, int $entityId): void
    {
        foreach ($this->handlers as $handler) {
            $handler->onEntityAction($action, $entityId);
        }
    }
}
