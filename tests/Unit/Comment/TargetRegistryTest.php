<?php declare(strict_types=1);

namespace Tests\Unit\Comment;

use App\Comment\TargetProviderInterface;
use App\Comment\TargetRegistry;
use PHPUnit\Framework\TestCase;

class TargetRegistryTest extends TestCase
{
    public function testProviderForReturnsTheMatchingProvider(): void
    {
        // Arrange
        $registry = new TargetRegistry([$this->makeProvider('event'), $this->makeProvider('photo')]);

        // Act
        $provider = $registry->providerFor('photo');

        // Assert
        self::assertSame('photo', $provider?->getTypeKey());
    }

    public function testUnknownTypeHasNoProvider(): void
    {
        // Arrange
        $registry = new TargetRegistry([$this->makeProvider('event')]);

        // Act + Assert
        self::assertNull($registry->providerFor('nope'));
        self::assertFalse($registry->has('nope'));
        self::assertTrue($registry->has('event'));
    }

    private function makeProvider(string $typeKey): TargetProviderInterface
    {
        $provider = $this->createStub(TargetProviderInterface::class);
        $provider->method('getTypeKey')->willReturn($typeKey);

        return $provider;
    }
}
