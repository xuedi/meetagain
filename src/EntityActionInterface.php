<?php declare(strict_types=1);

namespace App;

use App\Enum\EntityAction;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Reacts to an entity's create/update/delete. Called after flush, so the row has an id.
 */
#[AutoconfigureTag]
interface EntityActionInterface
{
    public function onEntityAction(EntityAction $action, int $entityId): void;
}
