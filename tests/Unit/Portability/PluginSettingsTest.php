<?php declare(strict_types=1);

namespace Tests\Unit\Portability;

use App\Portability\PluginSettings;
use App\Publisher\PluginSettings\Data;
use App\Publisher\PluginSettings\DescriptorInterface;
use App\Publisher\PluginSettings\Resolver;
use App\Publisher\PluginSettings\SecretKeysInterface;
use App\Publisher\PluginSettings\StoreInterface;
use App\Service\Admin\PluginSettingsService;
use Override;
use PHPUnit\Framework\TestCase;

final class PluginSettingsTest extends TestCase
{
    /** @var array<string, object> */
    private array $stored = [];

    /** @var list<array{key: string, values: array<string, mixed>, scopeId: string|null}> */
    private array $saved = [];

    public function testExportedSettingsLeaveTheirSecretKeysBehind(): void
    {
        // Arrange
        $this->stored['bgg'] = $this->data(['adapter' => 'bgg', 'encryptedBggToken' => 'cipher'], ['encryptedBggToken']);

        // Act
        $block = $this->pluginSettings([$this->descriptor('bgg', 'boardgames')])->export(['boardgames']);

        // Assert
        static::assertSame(['bgg' => ['adapter' => 'bgg']], $block);
    }

    public function testUnlistedPluginsAreLeftOutAndTheRestSortedByKey(): void
    {
        // Arrange
        $descriptors = [
            $this->descriptor('films', 'films'),
            $this->descriptor('dishes', 'dishes'),
            $this->descriptor('glossary', 'glossary'),
            $this->descriptor('books', 'books'),
        ];
        $this->stored['films'] = $this->data(['lookup' => 'tmdb']);
        $this->stored['dishes'] = $this->data(['phoneticInList' => true]);
        $this->stored['glossary'] = $this->data(['trainerEnabled' => true]);
        $this->stored['books'] = $this->data(['circulation' => true]);

        // Act
        $block = $this->pluginSettings($descriptors)->export(['films', 'glossary', 'books']);

        // Assert
        static::assertSame(['books' => ['circulation' => true], 'films' => ['lookup' => 'tmdb'], 'glossary' => ['trainerEnabled' => true]], $block);
    }

    public function testApplyingKeepsTheSecretAlreadyStoredHere(): void
    {
        // Arrange
        $this->stored['bgg'] = $this->data(['adapter' => null, 'encryptedBggToken' => 'local-cipher'], ['encryptedBggToken']);

        // Act
        $this->pluginSettings([$this->descriptor('bgg', 'boardgames')])->apply(['bgg' => ['adapter' => 'bgg', 'encryptedBggToken' => 'foreign-cipher']]);

        // Assert
        static::assertSame([['key' => 'bgg', 'values' => ['adapter' => 'bgg', 'encryptedBggToken' => 'local-cipher'], 'scopeId' => null]], $this->saved);
    }

    public function testApplyingWithoutAStoredRecordStartsFromTheNeutralDefault(): void
    {
        // Arrange
        $descriptor = $this->descriptor('books', 'books', default: $this->data(['circulation' => false, 'trustSystem' => false]));

        // Act
        $this->pluginSettings([$descriptor])->apply(['books' => ['circulation' => true]]);

        // Assert
        static::assertSame([['key' => 'books', 'values' => ['circulation' => true, 'trustSystem' => false], 'scopeId' => null]], $this->saved);
    }

    public function testUnknownAndMalformedSectionsAreIgnored(): void
    {
        // Arrange
        $descriptors = [$this->descriptor('films', 'films'), $this->descriptor('books', 'books')];
        $this->stored['films'] = $this->data(['lookup' => 'tmdb']);
        $this->stored['books'] = $this->data(['circulation' => false]);

        // Act
        $this->pluginSettings($descriptors)->apply(['unknown' => ['a' => 1], 'films' => ['lookup' => 'omdb'], 'books' => 'yes']);

        // Assert
        static::assertSame([['key' => 'films', 'values' => ['lookup' => 'omdb'], 'scopeId' => null]], $this->saved);
    }

    /**
     * @param list<DescriptorInterface> $descriptors
     */
    private function pluginSettings(array $descriptors): PluginSettings
    {
        $service = new PluginSettingsService($descriptors);

        return new PluginSettings($service, new Resolver($service, [$this->store()], []));
    }

    private function descriptor(string $key, string $pluginKey, ?Data $default = null): DescriptorInterface
    {
        $descriptor = $this->createStub(DescriptorInterface::class);
        $descriptor->method('getKey')->willReturn($key);
        $descriptor->method('getPluginKey')->willReturn($pluginKey);
        $descriptor->method('createDefault')->willReturn($default ?? $this->data([]));
        $descriptor->method('getPriority')->willReturn(0);

        return $descriptor;
    }

    private function store(): StoreInterface
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('supports')->willReturnCallback(static fn(string $key, ?string $scopeId): bool => $scopeId === null);
        $store->method('load')->willReturnCallback(fn(string $key): ?object => $this->stored[$key] ?? null);
        $store
            ->method('save')
            ->willReturnCallback(function (string $key, object $data, ?string $scopeId): void {
                static::assertInstanceOf(Data::class, $data);
                $this->saved[] = ['key' => $key, 'values' => $data->toArray(), 'scopeId' => $scopeId];
            });

        return $store;
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string> $secretKeys
     */
    private function data(array $values, array $secretKeys = []): Data
    {
        $data = new class implements Data, SecretKeysInterface {
            /** @var array<string, mixed> */
            public array $values = [];

            /** @var list<string> */
            public array $secretKeys = [];

            #[Override]
            public function toArray(): array
            {
                return $this->values;
            }

            #[Override]
            public static function fromArray(array $raw): static
            {
                $data = new static();
                $data->values = $raw;

                return $data;
            }

            #[Override]
            public function getSecretKeys(): array
            {
                return $this->secretKeys;
            }
        };
        $data->values = $values;
        $data->secretKeys = $secretKeys;

        return $data;
    }
}
