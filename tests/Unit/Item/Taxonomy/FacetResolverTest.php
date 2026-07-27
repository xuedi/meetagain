<?php declare(strict_types=1);

namespace Tests\Unit\Item\Taxonomy;

use App\Item\Taxonomy\FacetResolver;
use App\Item\Taxonomy\FacetSelection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class FacetResolverTest extends TestCase
{
    public function testEmptySelectionWithoutARequest(): void
    {
        // Arrange
        $resolver = new FacetResolver(new RequestStack());

        // Act + Assert
        static::assertTrue($resolver->current()->isEmpty());
    }

    public function testBothFacetsAreReadFromTheQuery(): void
    {
        // Arrange
        $resolver = $this->resolverFor('/dishes?category=3&tag[]=7&tag[]=9');

        // Act
        $selection = $resolver->current();

        // Assert
        static::assertSame(3, $selection->category);
        static::assertSame([7, 9], $selection->tags);
    }

    public function testNonNumericFacetValuesAreIgnored(): void
    {
        // Arrange
        $resolver = $this->resolverFor('/dishes?category=&tag[]=nope&tag[]=9');

        // Act
        $selection = $resolver->current();

        // Assert
        static::assertNull($selection->category);
        static::assertSame([9], $selection->tags);
    }

    public function testRepeatedTagIsCountedOnce(): void
    {
        // Arrange
        $resolver = $this->resolverFor('/dishes?tag[]=9&tag[]=9');

        // Act + Assert
        static::assertSame([9], $resolver->current()->tags);
    }

    public function testUrlCarriesTheSelectionAsAQueryString(): void
    {
        // Arrange
        $resolver = new FacetResolver(new RequestStack());

        // Act
        $url = $resolver->urlFor('/en/dishes', new FacetSelection(3, [7]));

        // Assert
        static::assertSame('/en/dishes?category=3&tag%5B0%5D=7', $url);
    }

    public function testUrlOfAnEmptySelectionIsTheCleanPath(): void
    {
        // Arrange
        $resolver = new FacetResolver(new RequestStack());

        // Act + Assert
        static::assertSame('/en/dishes', $resolver->urlFor('/en/dishes', new FacetSelection()));
    }

    public function testSuppressionWindowHidesTheFacetsAndRestoresAfterwards(): void
    {
        // Arrange
        $resolver = $this->resolverFor('/dishes?category=3&tag[]=7');

        // Act
        $inside = $resolver->withoutFacets($resolver->current(...));

        // Assert
        static::assertTrue($inside->isEmpty());
        static::assertSame(3, $resolver->current()->category);
    }

    public function testSuppressionWindowRestoresWhenTheCallbackThrows(): void
    {
        // Arrange
        $resolver = $this->resolverFor('/dishes?category=3');

        // Act
        $caught = null;

        try {
            $resolver->withoutFacets(static fn(): never => throw new RuntimeException('boom'));
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }

        // Assert
        static::assertInstanceOf(RuntimeException::class, $caught);
        static::assertSame(3, $resolver->current()->category);
    }

    private function resolverFor(string $uri): FacetResolver
    {
        $stack = new RequestStack();
        $stack->push(Request::create($uri));

        return new FacetResolver($stack);
    }
}
