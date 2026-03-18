<?php declare(strict_types=1);

namespace App;

use App\Enum\EntityAction;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for plugins to react to entity lifecycle actions.
 * Plugins implement this to handle entity creation, updates, and deletion.
 *
 * Called AFTER the entity operation completes (after flush), ensuring:
 * - Entity has an ID (auto-increment assigned)
 * - Transaction is complete
 * - No UnitOfWork corruption
 */
#[AutoconfigureTag]
interface EntityActionInterface
{
    /**
     * Handle an entity lifecycle action.
     *
     * @param EntityAction $action The action that occurred (create, update, delete)
     * @param int $entityId The ID of the affected entity
     */
    public function onEntityAction(EntityAction $action, int $entityId): void;
}
