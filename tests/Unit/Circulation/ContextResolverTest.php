<?php declare(strict_types=1);

namespace Tests\Unit\Circulation;

use App\Circulation\ContextProviderInterface;
use App\Circulation\ContextResolver;
use App\Circulation\DefaultContextProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ContextResolverTest extends TestCase
{
    public function testHighestPriorityNonNullAnswerWins(): void
    {
        // Arrange
        $resolver = new ContextResolver([
            new DefaultContextProvider(),
            $this->provider('book-group-7', 10),
            $this->provider('never-reached', 5),
        ]);

        // Act
        $context = $resolver->resolve('book');

        // Assert
        self::assertSame('book-group-7', $context);
    }

    public function testAbstainingProviderFallsThroughToTheCoreDefault(): void
    {
        // Arrange
        $resolver = new ContextResolver([$this->provider(null, 10), new DefaultContextProvider()]);

        // Act
        $context = $resolver->resolve('book');

        // Assert
        self::assertSame('book', $context);
    }

    public function testAllAbstainingChainThrows(): void
    {
        // Arrange
        $resolver = new ContextResolver([$this->provider(null, 10)]);

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $resolver->resolve('book');
    }

    private function provider(?string $context, int $priority): ContextProviderInterface
    {
        return new class($context, $priority) implements ContextProviderInterface {
            public function __construct(private readonly ?string $context, private readonly int $priority) {}

            public function getContext(string $itemType): ?string
            {
                return $this->context;
            }

            public function getPriority(): int
            {
                return $this->priority;
            }
        };
    }
}
