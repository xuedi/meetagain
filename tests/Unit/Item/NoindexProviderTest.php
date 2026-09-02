<?php declare(strict_types=1);

namespace Tests\Unit\Item;

use App\Item\ListRegistry;
use App\Item\NoindexProvider;
use App\Item\Tag\FacetSelection;
use App\Item\Tag\FacetService;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class NoindexProviderTest extends TestCase
{
    private Stub&FacetService $facetServiceStub;
    private Stub&ListRegistry $listRegistryStub;
    private NoindexProvider $subject;

    protected function setUp(): void
    {
        $this->facetServiceStub = $this->createStub(FacetService::class);
        $this->listRegistryStub = $this->createStub(ListRegistry::class);
        $this->subject = new NoindexProvider($this->facetServiceStub, $this->listRegistryStub);
    }

    public function testDefersOnAnIndexableRouteWithoutFacets(): void
    {
        // Arrange
        $this->facetServiceStub->method('current')->willReturn(new FacetSelection());
        $this->listRegistryStub->method('isDetailRouteIndexable')->willReturn(true);

        // Act
        $result = $this->subject->shouldNoindex($this->requestForRoute('app_book_detail'));

        // Assert
        static::assertFalse($result);
    }

    public function testVetoesWhenFacetsNarrowTheListing(): void
    {
        // Arrange
        $this->facetServiceStub->method('current')->willReturn(new FacetSelection([7]));
        $this->listRegistryStub->method('isDetailRouteIndexable')->willReturn(true);

        // Act
        $result = $this->subject->shouldNoindex($this->requestForRoute('app_book_list'));

        // Assert
        static::assertTrue($result);
    }

    public function testVetoesOnADetailRouteTheTypeDeclaresNonIndexable(): void
    {
        // Arrange
        $this->facetServiceStub->method('current')->willReturn(new FacetSelection());
        $this->listRegistryStub->method('isDetailRouteIndexable')->willReturn(false);

        // Act
        $result = $this->subject->shouldNoindex($this->requestForRoute('app_dish_detail'));

        // Assert
        static::assertTrue($result);
    }

    public function testDefersWhenTheRequestCarriesNoRoute(): void
    {
        // Arrange
        $this->facetServiceStub->method('current')->willReturn(new FacetSelection());
        $this->listRegistryStub->method('isDetailRouteIndexable')->willReturn(false);

        // Act
        $result = $this->subject->shouldNoindex(new Request());

        // Assert
        static::assertFalse($result);
    }

    private function requestForRoute(string $route): Request
    {
        $request = new Request();
        $request->attributes->set('_route', $route);

        return $request;
    }
}
