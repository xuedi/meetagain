<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Publisher\PluginSettings;

use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Entity\FilmclubSettings;
use Plugin\Filmclub\Form\FilmclubSettingsType;
use Plugin\Filmclub\Publisher\PluginSettings\FilmclubSettingsProvider;
use Plugin\Filmclub\Service\FilmclubSettingsService;
use Symfony\Component\Form\FormInterface;

class FilmclubSettingsProviderTest extends TestCase
{
    public function testGetKeyReturnsFilmclub(): void
    {
        // Arrange
        $provider = new FilmclubSettingsProvider($this->createStub(FilmclubSettingsService::class));

        // Act + Assert
        static::assertSame('filmclub', $provider->getKey());
    }

    public function testGetFormTypeReturnsFilmclubSettingsType(): void
    {
        // Arrange
        $provider = new FilmclubSettingsProvider($this->createStub(FilmclubSettingsService::class));

        // Act + Assert
        static::assertSame(FilmclubSettingsType::class, $provider->getFormType());
    }

    public function testLoadDataReturnsGlobalSettings(): void
    {
        // Arrange
        $settings = new FilmclubSettings();
        $service = $this->createStub(FilmclubSettingsService::class);
        $service->method('getOrCreateGlobal')->willReturn($settings);
        $provider = new FilmclubSettingsProvider($service);

        // Act
        $result = $provider->loadData();

        // Assert
        static::assertSame($settings, $result);
    }

    public function testSaveSetsTmdbKeyFromUnmappedField(): void
    {
        // Arrange
        $settings = new FilmclubSettings();
        $service = $this->createMock(FilmclubSettingsService::class);
        $service->method('encryptKey')->willReturn('encrypted-blob');
        $service->expects(static::once())->method('save')->with($settings);

        $provider = new FilmclubSettingsProvider($service);
        $form = $this->makeFormStub(tmdbKey: 'plaintext', clearTmdb: false, omdbKey: null, clearOmdb: false);

        // Act
        $provider->save($settings, $form);

        // Assert
        static::assertSame('encrypted-blob', $settings->getEncryptedTmdbKey());
        static::assertNull($settings->getEncryptedOmdbKey());
    }

    public function testSaveClearsTmdbKeyWhenClearChecked(): void
    {
        // Arrange
        $settings = new FilmclubSettings();
        $settings->setEncryptedTmdbKey('previous-value');
        $service = $this->createMock(FilmclubSettingsService::class);
        $service->expects(static::once())->method('save')->with($settings);

        $provider = new FilmclubSettingsProvider($service);
        $form = $this->makeFormStub(tmdbKey: 'should-be-ignored', clearTmdb: true, omdbKey: null, clearOmdb: false);

        // Act
        $provider->save($settings, $form);

        // Assert
        static::assertNull($settings->getEncryptedTmdbKey());
    }

    public function testSaveLeavesTmdbUntouchedWhenBothEmpty(): void
    {
        // Arrange
        $settings = new FilmclubSettings();
        $settings->setEncryptedTmdbKey('previous-value');
        $service = $this->createMock(FilmclubSettingsService::class);
        $service->expects(static::once())->method('save')->with($settings);

        $provider = new FilmclubSettingsProvider($service);
        $form = $this->makeFormStub(tmdbKey: null, clearTmdb: false, omdbKey: null, clearOmdb: false);

        // Act
        $provider->save($settings, $form);

        // Assert
        static::assertSame('previous-value', $settings->getEncryptedTmdbKey());
    }

    private function makeFormStub(
        ?string $tmdbKey,
        bool $clearTmdb,
        ?string $omdbKey,
        bool $clearOmdb,
    ): FormInterface {
        $fields = [
            'tmdbKey' => $this->fieldStub($tmdbKey),
            'clearTmdbKey' => $this->fieldStub($clearTmdb),
            'omdbKey' => $this->fieldStub($omdbKey),
            'clearOmdbKey' => $this->fieldStub($clearOmdb),
        ];

        $form = $this->createStub(FormInterface::class);
        $form->method('get')->willReturnCallback(static fn (string $name) => $fields[$name]);

        return $form;
    }

    private function fieldStub(mixed $value): FormInterface
    {
        $field = $this->createStub(FormInterface::class);
        $field->method('getData')->willReturn($value);

        return $field;
    }
}
