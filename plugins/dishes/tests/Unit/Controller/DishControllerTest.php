<?php declare(strict_types=1);

namespace Plugin\Dishes\Tests\Unit\Controller;

use App\Activity\ActivityService;
use App\Item\ListRegistry;
use App\Service\Seo\BreadcrumbBuilder;
use App\Item\Tag\AssignmentFormHelper;
use App\Item\Tag\TagService;
use App\Item\TranslationFormHelper;
use PHPUnit\Framework\TestCase;
use Plugin\Dishes\Controller\DishController;
use Plugin\Dishes\Service\ConfigService;
use Plugin\Dishes\Service\DishImageService;
use Plugin\Dishes\Service\DishService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DishControllerTest extends TestCase
{
    public function testDetailPageIsNotFoundWhenTheTypeIsNotRegistered(): void
    {
        // Arrange
        $dishService = $this->createMock(DishService::class);
        $dishService->expects(self::never())->method('get');

        $registry = $this->createStub(ListRegistry::class);
        $registry->method('has')->willReturn(false);

        $controller = new DishController(
            $dishService,
            $this->createStub(DishImageService::class),
            $this->createStub(ActivityService::class),
            $this->createStub(ConfigService::class),
            $this->createStub(TranslationFormHelper::class),
            $this->createStub(AssignmentFormHelper::class),
            $this->createStub(TagService::class),
        );

        // Assert
        $this->expectException(NotFoundHttpException::class);

        // Act
        $controller->show(3, new Request(), $registry, $this->breadcrumbBuilder());
    }

    private function breadcrumbBuilder(): BreadcrumbBuilder
    {
        return new BreadcrumbBuilder(
            $this->createStub(UrlGeneratorInterface::class),
            $this->createStub(TranslatorInterface::class),
            new RequestStack(),
        );
    }
}
