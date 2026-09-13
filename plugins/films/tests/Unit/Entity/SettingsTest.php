<?php declare(strict_types=1);

namespace Plugin\Films\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Plugin\Films\Entity\ExternalSource;
use Plugin\Films\Entity\Settings;

final class SettingsTest extends TestCase
{
    public function testTheSettingsSurviveTheirArrayForm(): void
    {
        // Arrange
        $settings = new Settings()->setAdapter(ExternalSource::Tmdb)->setEncryptedTmdbKey('cipher-t')->setEncryptedOmdbKey('cipher-o');

        // Act
        $restored = Settings::fromArray($settings->toArray());

        // Assert
        static::assertSame(ExternalSource::Tmdb, $restored->getAdapter());
        static::assertSame('cipher-t', $restored->getEncryptedTmdbKey());
        static::assertSame('cipher-o', $restored->getEncryptedOmdbKey());
    }

    public function testAnUnknownAdapterReadsAsNone(): void
    {
        // Act
        $settings = Settings::fromArray(['adapter' => 'imdb']);

        // Assert
        static::assertNull($settings->getAdapter());
        static::assertNull($settings->getEncryptedTmdbKey());
    }

    public function testTheEncryptedKeysAreSecret(): void
    {
        // Act
        $secretKeys = new Settings()->getSecretKeys();

        // Assert
        static::assertSame(['encryptedTmdbKey', 'encryptedOmdbKey'], $secretKeys);
        static::assertSame([], array_diff($secretKeys, array_keys(new Settings()->toArray())));
    }
}
