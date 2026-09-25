<?php declare(strict_types=1);

namespace Plugin\Karaoke\Tests\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Plugin\Karaoke\ValueObject\Config;

class ConfigTest extends TestCase
{
    public function testTheDefaultLooksUpWithLrclibAndHasNoKey(): void
    {
        // Arrange + Act
        $config = new Config();

        // Assert
        static::assertTrue($config->isLookupEnabled());
        static::assertTrue($config->isLrclibEnabled());
        static::assertNull($config->getEncryptedYoutubeApiKey());
    }

    public function testToArrayFromArrayRoundTrip(): void
    {
        // Arrange
        $config = new Config()
            ->setLookupEnabled(false)
            ->setLrclibEnabled(false)
            ->setEncryptedYoutubeApiKey('cipher');

        // Act
        $restored = Config::fromArray($config->toArray());

        // Assert
        static::assertFalse($restored->isLookupEnabled());
        static::assertFalse($restored->isLrclibEnabled());
        static::assertSame('cipher', $restored->getEncryptedYoutubeApiKey());
    }

    public function testAStoredRecordWithoutTheFlagsKeepsTheLookupOn(): void
    {
        // Arrange + Act
        $restored = Config::fromArray([]);

        // Assert
        static::assertTrue($restored->isLookupEnabled());
        static::assertTrue($restored->isLrclibEnabled());
    }

    public function testTheEncryptedKeyIsTheOnlySecret(): void
    {
        // Arrange
        $config = new Config()->setEncryptedYoutubeApiKey('');

        // Act
        $secretKeys = $config->getSecretKeys();

        // Assert
        static::assertSame(['encryptedYoutubeApiKey'], $secretKeys);
        static::assertNull($config->getEncryptedYoutubeApiKey());
    }
}
