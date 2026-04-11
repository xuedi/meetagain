<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Enum\EntityAction;
use App\Service\Cms\CmsCacheInvalidationHandler;
use App\Service\Cms\CmsPageCacheService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CmsCacheInvalidationHandlerTest extends TestCase
{
    #[DataProvider('provideCmsInvalidationActions')]
    public function testOnEntityActionInvalidatesCmsPageAndMenus(EntityAction $action): void
    {
        // Arrange
        $cacheServiceMock = $this->createMock(CmsPageCacheService::class);
        $cacheServiceMock->expects($this->once())->method('invalidatePage')->with(7);
        $cacheServiceMock->expects($this->once())->method('invalidateMenuCaches');

        $handler = new CmsCacheInvalidationHandler($cacheServiceMock);

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
        $cacheServiceMock = $this->createMock(CmsPageCacheService::class);
        $cacheServiceMock->expects($this->never())->method('invalidatePage');
        $cacheServiceMock->expects($this->never())->method('invalidateMenuCaches');

        $handler = new CmsCacheInvalidationHandler($cacheServiceMock);

        // Act
        $handler->onEntityAction(EntityAction::UpdateEvent, 7);

        // Assert: expectations verified automatically
    }
}
