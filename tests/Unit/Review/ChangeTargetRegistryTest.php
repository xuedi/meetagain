<?php declare(strict_types=1);

namespace Tests\Unit\Review;

use App\Review\ChangeTargetProviderInterface;
use App\Review\ChangeTargetRegistry;
use PHPUnit\Framework\TestCase;

class ChangeTargetRegistryTest extends TestCase
{
    public function testProviderOfAnActivePluginIsFoundByTargetType(): void
    {
        // Arrange
        $provider = $this->provider('glossary');
        $registry = new ChangeTargetRegistry([$provider]);

        // Act
        $found = $registry->providerFor('glossary');

        // Assert
        self::assertSame($provider, $found);
        self::assertTrue($registry->has('glossary'));
    }

    public function testUnknownTargetTypeHasNoProvider(): void
    {
        // Arrange
        $registry = new ChangeTargetRegistry([$this->provider('glossary')]);

        // Act
        $found = $registry->providerFor('book');

        // Assert
        self::assertNull($found);
    }

    private function provider(string $targetType): ChangeTargetProviderInterface
    {
        $provider = $this->createStub(ChangeTargetProviderInterface::class);
        $provider->method('getTargetType')->willReturn($targetType);

        return $provider;
    }
}
