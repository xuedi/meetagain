<?php declare(strict_types=1);

namespace Plugin\Books\Tests\Unit\Controller;

use App\Activity\ActivityService;
use App\Item\ListRegistry;
use App\Service\Seo\BreadcrumbBuilder;
use App\Item\Tag\AssignmentFormHelper;
use App\Item\Tag\TagService;
use PHPUnit\Framework\TestCase;
use Plugin\Books\Controller\BookController;
use Plugin\Books\Service\BookService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookControllerTest extends TestCase
{
    public function testDetailPageIsNotFoundWhenTheTypeIsNotRegistered(): void
    {
        // Arrange
        $bookService = $this->createMock(BookService::class);
        $bookService->expects(self::never())->method('get');

        $registry = $this->createStub(ListRegistry::class);
        $registry->method('has')->willReturn(false);

        $controller = new BookController(
            $bookService,
            $this->createStub(ActivityService::class),
            $this->createStub(AssignmentFormHelper::class),
            $this->createStub(TagService::class),
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
