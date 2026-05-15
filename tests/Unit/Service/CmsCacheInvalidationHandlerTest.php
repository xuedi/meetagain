<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Enum\EntityAction;
use App\Service\Cms\CmsCacheInvalidationHandler;
use App\Service\Cms\CmsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CmsCacheInvalidationHandlerTest extends TestCase
{
    #[DataProvider('provideCmsInvalidationActions')]
    public function testOnEntityActionInvalidatesCmsPageAndMenus(EntityAction $action): void
    {
        // Arrange
        $cmsServiceMock = $this->createMock(CmsService::class);
        $cmsServiceMock->expects($this->once())->method('invalidatePage')->with(7);
        $cmsServiceMock->expects($this->once())->method('invalidateMenuCaches');

        $handler = new CmsCacheInvalidationHandler($cmsServiceMock);

        // Act
        $handler->onEntityAction($action, 7);

        // Assert: expectations verified automatically
    }

    public static function provideCmsInvalidationActions(): iterable
    {
        yield 'UpdateCms triggers invalidation' => [EntityAction::UpdateCms];
        yield 'DeleteCms triggers invalidation' => [EntityAction::DeleteCms];
    }

    public function testOnEntityActionIgnoresUnrelatedActions(): void
    {
        // Arrange
        $cmsServiceMock = $this->createMock(CmsService::class);
        $cmsServiceMock->expects($this->never())->method('invalidatePage');
        $cmsServiceMock->expects($this->never())->method('invalidateMenuCaches');

        $handler = new CmsCacheInvalidationHandler($cmsServiceMock);

        // Act
        $handler->onEntityAction(EntityAction::UpdateEvent, 7);

        // Assert: expectations verified automatically
    }

    public function testOnEntityActionInvalidatesPageOnlyForBlockUpdate(): void
    {
        // Arrange: block edits must NOT bust the menu cache - block content doesn't affect menu placement
        $cmsServiceMock = $this->createMock(CmsService::class);
        $cmsServiceMock->expects($this->once())->method('invalidatePage')->with(7);
        $cmsServiceMock->expects($this->never())->method('invalidateMenuCaches');

        $handler = new CmsCacheInvalidationHandler($cmsServiceMock);

        // Act
        $handler->onEntityAction(EntityAction::UpdateCmsBlock, 7);

        // Assert: expectations verified automatically
    }
}
