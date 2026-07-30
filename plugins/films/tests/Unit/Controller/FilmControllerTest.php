<?php declare(strict_types=1);

namespace Plugin\Films\Tests\Unit\Controller;

use App\Activity\ActivityService;
use App\Item\ListRegistry;
use App\Service\Seo\BreadcrumbBuilder;
use App\Item\Taxonomy\AssignmentFormHelper;
use App\Item\Taxonomy\TaxonomyService;
use PHPUnit\Framework\TestCase;
use Plugin\Films\Controller\FilmController;
use Plugin\Films\Service\FilmLookupResolver;
use Plugin\Films\Service\FilmService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FilmControllerTest extends TestCase
{
    public function testDetailPageIsNotFoundWhenTheTypeIsNotRegistered(): void
    {
        // Arrange
        $filmService = $this->createMock(FilmService::class);
        $filmService->expects(self::never())->method('get');

        $registry = $this->createStub(ListRegistry::class);
        $registry->method('has')->willReturn(false);

        $controller = new FilmController(
            $filmService,
            $this->createStub(FilmLookupResolver::class),
            $this->createStub(ActivityService::class),
            $this->createStub(AssignmentFormHelper::class),
            $this->createStub(TaxonomyService::class),
        );

        // Assert
        $this->expectException(NotFoundHttpException::class);

        // Act
        $controller->show(3, $registry, $this->breadcrumbBuilder());
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
