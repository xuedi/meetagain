<?php declare(strict_types=1);

namespace App;

use App\Enum\EntityAction;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class EntityActionDispatcher
{
    /**
     * @param iterable<EntityActionInterface> $handlers
     */
    public function __construct(
        #[AutowireIterator(EntityActionInterface::class)]
        private iterable $handlers,
    ) {}

    public function dispatch(EntityAction $action, int $entityId): void
    {
        foreach ($this->handlers as $handler) {
            $handler->onEntityAction($action, $entityId);
        }
    }
}
