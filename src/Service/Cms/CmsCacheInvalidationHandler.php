<?php declare(strict_types=1);

namespace App\Service\Cms;

use App\EntityActionInterface;
use App\Enum\EntityAction;

readonly class CmsCacheInvalidationHandler implements EntityActionInterface
{
    public function __construct(
        private CmsService $cmsService,
    ) {}

    public function onEntityAction(EntityAction $action, int $entityId): void
    {
        match ($action) {
            EntityAction::UpdateCms, EntityAction::DeleteCms => $this->invalidateCmsAndMenus($entityId),
            EntityAction::UpdateCmsBlock => $this->cmsService->invalidatePage($entityId),
            default => null,
        };
    }

    private function invalidateCmsAndMenus(int $entityId): void
    {
        $this->cmsService->invalidatePage($entityId);
        $this->cmsService->invalidateMenuCaches();
    }
}
