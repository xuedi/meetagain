<?php declare(strict_types=1);

namespace Plugin\Boardgames\Tests\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Plugin\Boardgames\Enum\ExternalSource;
use Plugin\Boardgames\ValueObject\Config;

class ConfigTest extends TestCase
{
    public function testNeutralDefaultHasNoAdapterNoTokenAndNoCirculation(): void
    {
        // Arrange + Act
        $config = new Config();

        // Assert
        static::assertNull($config->getAdapter());
        static::assertNull($config->getEncryptedBggToken());
        static::assertFalse($config->isCirculation());
        static::assertFalse($config->isTrustActive());
    }

    public function testToArrayFromArrayRoundTrip(): void
    {
        // Arrange
        $config = new Config()
            ->setAdapter(ExternalSource::Bgg)
            ->setEncryptedBggToken('cipher')
            ->setCirculation(true)
            ->setTrustSystem(true);

        // Act
        $restored = Config::fromArray($config->toArray());

        // Assert
        static::assertSame(ExternalSource::Bgg, $restored->getAdapter());
        static::assertSame('cipher', $restored->getEncryptedBggToken());
        static::assertTrue($restored->isTrustActive());
    }

    public function testAnUnknownAdapterValueFallsBackToNone(): void
    {
        // Arrange + Act
        $restored = Config::fromArray(['adapter' => 'atlas']);

        // Assert
        static::assertNull($restored->getAdapter());
    }

    public function testAnEmptyTokenIsStoredAsNull(): void
    {
        // Arrange + Act
        $config = new Config()->setEncryptedBggToken('');

        // Assert
        static::assertNull($config->getEncryptedBggToken());
    }

    public function testTrustIsInactiveWhileCirculationIsOff(): void
    {
        // Arrange + Act
        $config = new Config()->setTrustSystem(true);

        // Assert
        static::assertFalse($config->isTrustActive());
    }
}
