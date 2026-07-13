<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Publisher\PluginSettings;

use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Entity\FilmclubSettings;
use Plugin\Filmclub\Form\FilmclubSettingsType;
use Plugin\Filmclub\Publisher\PluginSettings\FilmclubSettingsDescriptor;
use Plugin\Filmclub\Publisher\PluginSettings\FilmclubSettingsStore;
use Plugin\Filmclub\Service\FilmclubSettingsService;
use Symfony\Component\Form\FormInterface;

class FilmclubSettingsDescriptorTest extends TestCase
{
    public function testGetKeyReturnsFilmclub(): void
    {
        // Arrange
        $descriptor = new FilmclubSettingsDescriptor($this->createStub(FilmclubSettingsService::class));

        // Act + Assert
        static::assertSame('filmclub', $descriptor->getKey());
    }

    public function testGetFormTypeReturnsFilmclubSettingsType(): void
    {
        // Arrange
        $descriptor = new FilmclubSettingsDescriptor($this->createStub(FilmclubSettingsService::class));

        // Act + Assert
        static::assertSame(FilmclubSettingsType::class, $descriptor->getFormType());
    }

    public function testCreateDefaultReturnsFreshSettings(): void
    {
        // Arrange
        $descriptor = new FilmclubSettingsDescriptor($this->createStub(FilmclubSettingsService::class));

        // Act
        $default = $descriptor->createDefault();

        // Assert
        static::assertInstanceOf(FilmclubSettings::class, $default);
        static::assertNull($default->getEncryptedTmdbKey());
    }

    public function testFormOptionsReflectStoredKeys(): void
    {
        // Arrange
        $descriptor = new FilmclubSettingsDescriptor($this->createStub(FilmclubSettingsService::class));
        $settings = (new FilmclubSettings())->setEncryptedTmdbKey('blob');

        // Act
        $options = $descriptor->getFormOptions($settings);

        // Assert
        static::assertTrue($options['tmdb_key_set']);
        static::assertFalse($options['omdb_key_set']);
    }

    public function testApplyFormSetsTmdbKeyFromUnmappedField(): void
    {
        // Arrange
        $settings = new FilmclubSettings();
        $service = $this->createStub(FilmclubSettingsService::class);
        $service->method('encryptKey')->willReturn('encrypted-blob');

        $descriptor = new FilmclubSettingsDescriptor($service);
        $form = $this->makeFormStub(tmdbKey: 'plaintext', clearTmdb: false, omdbKey: null, clearOmdb: false);

        // Act
        $descriptor->applyForm($settings, $form);

        // Assert
        static::assertSame('encrypted-blob', $settings->getEncryptedTmdbKey());
        static::assertNull($settings->getEncryptedOmdbKey());
    }

    public function testApplyFormClearsTmdbKeyWhenClearChecked(): void
    {
        // Arrange
        $settings = (new FilmclubSettings())->setEncryptedTmdbKey('previous-value');
        $descriptor = new FilmclubSettingsDescriptor($this->createStub(FilmclubSettingsService::class));
        $form = $this->makeFormStub(tmdbKey: 'should-be-ignored', clearTmdb: true, omdbKey: null, clearOmdb: false);

        // Act
        $descriptor->applyForm($settings, $form);

        // Assert
        static::assertNull($settings->getEncryptedTmdbKey());
    }

    public function testApplyFormLeavesTmdbUntouchedWhenBothEmpty(): void
    {
        // Arrange
        $settings = (new FilmclubSettings())->setEncryptedTmdbKey('previous-value');
        $descriptor = new FilmclubSettingsDescriptor($this->createStub(FilmclubSettingsService::class));
        $form = $this->makeFormStub(tmdbKey: null, clearTmdb: false, omdbKey: null, clearOmdb: false);

        // Act
        $descriptor->applyForm($settings, $form);

        // Assert
        static::assertSame('previous-value', $settings->getEncryptedTmdbKey());
    }

    public function testStoreLoadsGlobalSettings(): void
    {
        // Arrange
        $settings = new FilmclubSettings();
        $service = $this->createStub(FilmclubSettingsService::class);
        $service->method('getOrCreateGlobal')->willReturn($settings);
        $store = new FilmclubSettingsStore($service);

        // Act + Assert
        static::assertSame($settings, $store->load('filmclub', null));
    }

    public function testStoreSupportsOnlyGlobalFilmclubScope(): void
    {
        // Arrange
        $store = new FilmclubSettingsStore($this->createStub(FilmclubSettingsService::class));

        // Act + Assert
        static::assertTrue($store->supports('filmclub', null));
        static::assertFalse($store->supports('filmclub', '7'));
        static::assertFalse($store->supports('glossary', null));
    }

    public function testStoreSavePersistsThroughService(): void
    {
        // Arrange
        $settings = new FilmclubSettings();
        $service = $this->createMock(FilmclubSettingsService::class);
        $service->expects(static::once())->method('save')->with($settings);
        $store = new FilmclubSettingsStore($service);

        // Act
        $store->save('filmclub', $settings, null);

        // Assert - mock verifies save() was called
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
