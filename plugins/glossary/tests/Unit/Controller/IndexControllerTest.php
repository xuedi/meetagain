<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Controller;

use App\Item\ListRegistry;
use App\Service\Seo\BreadcrumbBuilder;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Controller\IndexController;
use Plugin\Glossary\Service\GlossaryService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class IndexControllerTest extends TestCase
{
    public function testDetailPageIsNotFoundWhenTheTypeIsNotRegistered(): void
    {
        // Arrange
        $glossaryService = $this->createMock(GlossaryService::class);
        $glossaryService->expects(self::never())->method('get');

        $registry = $this->createStub(ListRegistry::class);
        $registry->method('has')->willReturn(false);

        $controller = new IndexController($glossaryService);

        // Assert
        $this->expectException(NotFoundHttpException::class);

        // Act
        $controller->detail(3, $registry, $this->breadcrumbBuilder());
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
