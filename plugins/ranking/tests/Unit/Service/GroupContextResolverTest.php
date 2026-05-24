<?php declare(strict_types=1);

namespace Plugin\Ranking\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Plugin\Ranking\Service\GroupContextProviderInterface;
use Plugin\Ranking\Service\GroupContextResolver;

final class GroupContextResolverTest extends TestCase
{
    public function testFallsBackToDefaultWhenNoProvider(): void
    {
        // Arrange
        $resolver = new GroupContextResolver([]);

        // Act + Assert
        static::assertSame(GroupContextResolver::DEFAULT_GROUP_ID, $resolver->getCurrentGroupId());
    }

    public function testReturnsFirstNonNullFromHighestPriority(): void
    {
        // Arrange
        $low = $this->makeProvider(returns: 5, priority: 10);
        $high = $this->makeProvider(returns: 42, priority: 100);
        $mid = $this->makeProvider(returns: null, priority: 50);

        $resolver = new GroupContextResolver([$low, $high, $mid]);

        // Act
        $result = $resolver->getCurrentGroupId();

        // Assert
        static::assertSame(42, $result);
    }

    public function testSkipsNullProviders(): void
    {
        // Arrange
        $resolver = new GroupContextResolver([
            $this->makeProvider(null, 100),
            $this->makeProvider(7, 10),
        ]);

        // Act
        $result = $resolver->getCurrentGroupId();

        // Assert
        static::assertSame(7, $result);
    }

    private function makeProvider(?int $returns, int $priority): GroupContextProviderInterface
    {
        return new class($returns, $priority) implements GroupContextProviderInterface {
            public function __construct(private readonly ?int $returns, private readonly int $priority) {}

            public function getCurrentGroupId(): ?int
            {
                return $this->returns;
            }

            public function getCurrentGroupName(): ?string
            {
                return null;
            }

            public function getPriority(): int
            {
                return $this->priority;
            }
        };
    }
}
