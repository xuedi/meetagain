<?php declare(strict_types=1);

namespace Tests\Unit\Cms\ReservedSlug;

use App\Cms\ReservedSlug\ReservedSlugProviderInterface;
use App\Cms\ReservedSlug\ReservedSlugRegistry;
use App\Repository\CmsRepository;
use PHPUnit\Framework\TestCase;

class ReservedSlugRegistryTest extends TestCase
{
    public function testUnionsSlugsFromAllProviders(): void
    {
        // Arrange
        $registry = $this->buildRegistry([['about', 'pricing'], ['imprint']]);

        // Act
        $all = $registry->all();

        // Assert
        sort($all);
        static::assertSame(['about', 'imprint', 'pricing'], $all);
    }

    public function testNormalizesCaseAndWhitespace(): void
    {
        // Arrange
        $registry = $this->buildRegistry([[' About ', 'PRICING']]);

        // Act & Assert
        static::assertTrue($registry->isReserved('about'));
        static::assertTrue($registry->isReserved('  PrIcInG '));
        static::assertSame(['about', 'pricing'], $registry->all());
    }

    public function testEmptySlugsAreIgnored(): void
    {
        // Arrange
        $registry = $this->buildRegistry([['', '   ', 'about']]);

        // Act & Assert
        static::assertSame(['about'], $registry->all());
    }

    public function testFreeSlugIsNotReserved(): void
    {
        // Arrange
        $registry = $this->buildRegistry([['about']]);

        // Act & Assert
        static::assertFalse($registry->isReserved('summer-party'));
    }

    public function testOwnPersistedSlugIsAllowedOnEdit(): void
    {
        // Arrange - the page being edited already owns the reserved slug in the DB
        $registry = $this->buildRegistry([['imprint']], ['5' => 'imprint']);

        // Act & Assert
        static::assertFalse($registry->isReserved('imprint', 5));
    }

    public function testReservedSlugBlockedWhenAnotherPageOwnsIt(): void
    {
        // Arrange - page 9's persisted slug differs from the reserved slug it is being changed to
        $registry = $this->buildRegistry([['imprint']], ['9' => 'something-else']);

        // Act & Assert
        static::assertTrue($registry->isReserved('imprint', 9));
    }

    public function testProviderSlugWithoutBackingPageIsAlwaysReserved(): void
    {
        // Arrange - no Cms row backs the slug, so the id never matches
        $registry = $this->buildRegistry([['pricing']], []);

        // Act & Assert
        static::assertTrue($registry->isReserved('pricing', 7));
        static::assertTrue($registry->isReserved('pricing'));
    }

    /**
     * @param array<array<string>> $providerSlugs
     * @param array<string, string> $slugById
     */
    private function buildRegistry(array $providerSlugs, array $slugById = []): ReservedSlugRegistry
    {
        $providers = [];
        foreach ($providerSlugs as $slugs) {
            $provider = $this->createStub(ReservedSlugProviderInterface::class);
            $provider->method('getReservedSlugs')->willReturn($slugs);
            $providers[] = $provider;
        }

        $repo = $this->createStub(CmsRepository::class);
        $repo->method('findSlugById')->willReturnCallback(static fn(int $id): ?string => $slugById[(string) $id] ?? null);

        return new ReservedSlugRegistry($providers, $repo);
    }
}
