<?php declare(strict_types=1);

namespace App\Service;

use App\Enum\EntityAction;

readonly class CmsCacheInvalidationHandler implements EntityActionInterface
{
    public function __construct(
        private CmsPageCacheService $cmsPageCacheService,
    ) {}

    public function onEntityAction(EntityAction $action, int $entityId): void
    {
        match ($action) {
            EntityAction::UpdateCms, EntityAction::DeleteCms => $this->cmsPageCacheService->invalidatePage($entityId),
            default => null,
        };
    }
}
