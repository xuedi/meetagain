<?php declare(strict_types=1);

namespace Module\Proposal\Tests\Unit\Internal\Change;

use Module\Proposal\Contract\Change\TargetProviderInterface;
use Module\Proposal\Internal\Change\Registry;
use PHPUnit\Framework\TestCase;

class RegistryTest extends TestCase
{
    public function testProviderOfAnActivePluginIsFoundByTargetType(): void
    {
        // Arrange
        $provider = $this->provider('glossary');
        $registry = new Registry([$provider]);

        // Act
        $found = $registry->providerFor('glossary');

        // Assert
        self::assertSame($provider, $found);
        self::assertTrue($registry->has('glossary'));
    }

    public function testUnknownTargetTypeHasNoProvider(): void
    {
        // Arrange
        $registry = new Registry([$this->provider('glossary')]);

        // Act
        $found = $registry->providerFor('book');

        // Assert
        self::assertNull($found);
    }

    private function provider(string $targetType): TargetProviderInterface
    {
        $provider = $this->createStub(TargetProviderInterface::class);
        $provider->method('getTargetType')->willReturn($targetType);

        return $provider;
    }
}
