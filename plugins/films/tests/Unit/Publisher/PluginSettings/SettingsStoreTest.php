<?php declare(strict_types=1);

namespace Plugin\Films\Tests\Unit\Publisher\PluginSettings;

use PHPUnit\Framework\TestCase;
use Plugin\Films\Entity\ExternalSource;
use Plugin\Films\Entity\Settings;
use Plugin\Films\Publisher\PluginSettings\SettingsStore;
use Plugin\Films\Service\SettingsService;

final class SettingsStoreTest extends TestCase
{
    public function testSavingDetachedSettingsWritesOntoTheOneGlobalRow(): void
    {
        // Arrange
        $global = new Settings()
            ->setAdapter(ExternalSource::Omdb)
            ->setEncryptedOmdbKey('kept-before');
        $service = $this->createMock(SettingsService::class);
        $service->method('getOrCreateGlobal')->willReturn($global);
        $service->expects($this->once())->method('save')->with($this->identicalTo($global));
        $detached = new Settings()
            ->setAdapter(ExternalSource::Tmdb)
            ->setEncryptedTmdbKey('cipher-t')
            ->setEncryptedOmdbKey('kept-before');

        // Act
        new SettingsStore($service)->save('films', $detached, null);

        // Assert
        static::assertSame(ExternalSource::Tmdb, $global->getAdapter());
        static::assertSame('cipher-t', $global->getEncryptedTmdbKey());
        static::assertSame('kept-before', $global->getEncryptedOmdbKey());
    }

    public function testSavingTheGlobalRowItselfKeepsItsValues(): void
    {
        // Arrange
        $global = new Settings()->setAdapter(ExternalSource::Manual);
        $service = $this->createMock(SettingsService::class);
        $service->method('getOrCreateGlobal')->willReturn($global);
        $service->expects($this->once())->method('save')->with($this->identicalTo($global));

        // Act
        new SettingsStore($service)->save('films', $global, null);

        // Assert
        static::assertSame(ExternalSource::Manual, $global->getAdapter());
    }
}
