<?php declare(strict_types=1);

namespace Tests\Unit\Service\Test;

use App\Service\Test\RouteDiscoverer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

class RouteDiscovererTest extends TestCase
{
    private function makeDiscoverer(RouteCollection $collection): RouteDiscoverer
    {
        $generator = new UrlGenerator($collection, new RequestContext());

        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);
        $router->method('generate')->willReturnCallback(
            static fn(string $name, array $params = []): string => $generator->generate($name, $params),
        );

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return new RouteDiscoverer($router, $em);
    }

    /** @param string[] $urls */
    private static function paths(array $urls): array
    {
        return array_map(static fn(string $u): string => explode('?', $u, 2)[0], $urls);
    }

    public function testIncludesPlainGetRoutes(): void
    {
        // Arrange
        $collection = new RouteCollection();
        $collection->add('app_login', new Route('/login', methods: ['GET']));

        // Act
        $urls = $this->makeDiscoverer($collection)->discoverGetUrls();

        // Assert
        static::assertContains('/login', self::paths($urls));
    }

    public function testExcludesNonGetRoutes(): void
    {
        // Arrange
        $collection = new RouteCollection();
        $collection->add('app_post', new Route('/post', methods: ['POST']));
        $collection->add('app_get', new Route('/get', methods: ['GET']));

        // Act
        $urls = $this->makeDiscoverer($collection)->discoverGetUrls();

        // Assert
        static::assertSame(['/get'], self::paths($urls));
    }

    public function testSkipsRoutesMatchingSkipPatterns(): void
    {
        // Arrange
        $collection = new RouteCollection();
        $collection->add('app_logout', new Route('/logout', methods: ['GET']));
        $collection->add('app_item_delete', new Route('/item/delete', methods: ['GET']));
        $collection->add('app_user_toggle', new Route('/user/toggle', methods: ['GET']));
        $collection->add('app_install_step', new Route('/install/step', methods: ['GET']));
        $collection->add('_wdt_bar', new Route('/_wdt', methods: ['GET']));
        $collection->add('app_keep', new Route('/keep', methods: ['GET']));

        // Act
        $urls = $this->makeDiscoverer($collection)->discoverGetUrls();

        // Assert
        static::assertSame(['/keep'], self::paths($urls));
    }

    public function testFillsParamMapWithDefaults(): void
    {
        // Arrange
        $collection = new RouteCollection();
        $collection->add('app_with_id', new Route('/item/{id}', methods: ['GET']));
        $collection->add('app_with_user', new Route('/user/{userId}', methods: ['GET']));
        $collection->add('app_with_page', new Route('/list/{page}', methods: ['GET']));

        // Act
        $urls = $this->makeDiscoverer($collection)->discoverGetUrls();

        // Assert
        $paths = self::paths($urls);
        static::assertContains('/item/1', $paths);
        static::assertContains('/user/1', $paths);
        static::assertContains('/list/1', $paths);
    }

    public function testSilentlySkipsRoutesWithUnmappedRequiredParams(): void
    {
        // Arrange
        $collection = new RouteCollection();
        $collection->add('app_with_unknown', new Route('/thing/{unknownParam}', methods: ['GET']));
        $collection->add('app_simple', new Route('/simple', methods: ['GET']));

        // Act
        $urls = $this->makeDiscoverer($collection)->discoverGetUrls();

        // Assert
        static::assertSame(['/simple'], self::paths($urls));
    }

    public function testOutputIsDeduplicatedAndStable(): void
    {
        // Arrange
        $collection = new RouteCollection();
        $collection->add('app_a', new Route('/zebra', methods: ['GET']));
        $collection->add('app_b', new Route('/apple', methods: ['GET']));
        $collection->add('app_c_dup', new Route('/zebra', methods: ['GET']));

        // Act
        $urls = $this->makeDiscoverer($collection)->discoverGetUrls();

        // Assert
        $paths = self::paths($urls);
        static::assertSame(['/apple', '/zebra'], $paths);
        static::assertCount(2, $urls);
    }
}
