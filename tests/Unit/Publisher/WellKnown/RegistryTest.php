<?php declare(strict_types=1);

namespace Tests\Unit\Publisher\WellKnown;

use App\Publisher\WellKnown\Registry;
use App\Publisher\WellKnown\WellKnownDocument;
use App\Publisher\WellKnown\WellKnownProviderInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class RegistryTest extends TestCase
{
    public function testHighestPriorityProviderWins(): void
    {
        // Arrange
        $registry = new Registry([
            $this->provider('llms.txt', 0, 'core'),
            $this->provider('llms.txt', 100, 'plugin'),
        ]);

        // Act
        $document = $registry->resolve('llms.txt', new Request());

        // Assert
        self::assertSame('plugin', $document?->body);
    }

    public function testNullProviderFallsThroughToNext(): void
    {
        // Arrange
        $registry = new Registry([
            $this->provider('llms.txt', 100, null),
            $this->provider('llms.txt', 0, 'core'),
        ]);

        // Act
        $document = $registry->resolve('llms.txt', new Request());

        // Assert
        self::assertSame('core', $document?->body);
    }

    public function testAllProvidersDecliningResolvesToNull(): void
    {
        // Arrange
        $registry = new Registry([$this->provider('llms.txt', 0, null)]);

        // Act
        $document = $registry->resolve('llms.txt', new Request());

        // Assert
        self::assertNull($document);
    }

    #[DataProvider('unclaimedSuffixProvider')]
    public function testUnclaimedSuffixResolvesToNull(string $suffix): void
    {
        // Arrange
        $registry = new Registry([$this->provider('security.txt', 0, 'body')]);

        // Act
        $document = $registry->resolve($suffix, new Request());

        // Assert
        self::assertNull($document);
    }

    public static function unclaimedSuffixProvider(): iterable
    {
        yield 'unknown document' => ['agent-card.json'];
        yield 'traversal is only ever a map key' => ['../security.txt'];
        yield 'nested traversal' => ['mcp/../../security.txt'];
        yield 'empty suffix' => [''];
        yield 'prefix of a claimed suffix' => ['security'];
    }

    public function testSuffixesAreEnumerable(): void
    {
        // Arrange
        $registry = new Registry([
            $this->provider('security.txt', 0, 'body'),
            $this->provider('llms.txt', 0, 'body'),
            $this->provider('llms.txt', 100, 'body'),
        ]);

        // Act
        $suffixes = $registry->getSuffixes();

        // Assert
        self::assertEqualsCanonicalizing(['security.txt', 'llms.txt'], $suffixes);
    }

    private function provider(string $suffix, int $priority, ?string $body): WellKnownProviderInterface
    {
        $provider = $this->createStub(WellKnownProviderInterface::class);
        $provider->method('getSuffix')->willReturn($suffix);
        $provider->method('getPriority')->willReturn($priority);
        $provider->method('provide')->willReturn(
            $body === null ? null : WellKnownDocument::of($body, 'text/plain'),
        );

        return $provider;
    }
}
