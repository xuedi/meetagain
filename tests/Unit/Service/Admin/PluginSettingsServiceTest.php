<?php declare(strict_types=1);

namespace Tests\Unit\Service\Admin;

use App\Publisher\PluginSettings\PluginSettingsProviderInterface;
use App\Service\Admin\PluginSettingsService;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;

class PluginSettingsServiceTest extends TestCase
{
    public function testHasAnyReturnsFalseWithNoProviders(): void
    {
        // Arrange
        $service = new PluginSettingsService([]);

        // Act
        $result = $service->hasAny();

        // Assert
        static::assertFalse($result);
    }

    public function testHasAnyReturnsTrueWithProviders(): void
    {
        // Arrange
        $service = new PluginSettingsService([$this->makeProvider('a')]);

        // Act
        $result = $service->hasAny();

        // Assert
        static::assertTrue($result);
    }

    public function testGetProvidersReturnsThemInDescendingPriorityOrder(): void
    {
        // Arrange
        $low = $this->makeProvider('low', priority: 0);
        $high = $this->makeProvider('high', priority: 100);
        $mid = $this->makeProvider('mid', priority: 50);
        $service = new PluginSettingsService([$low, $high, $mid]);

        // Act
        $keys = array_keys($service->getProviders());

        // Assert
        static::assertSame(['high', 'mid', 'low'], $keys);
    }

    public function testGetProviderReturnsByKey(): void
    {
        // Arrange
        $provider = $this->makeProvider('filmclub');
        $service = new PluginSettingsService([$provider]);

        // Act + Assert
        static::assertSame($provider, $service->getProvider('filmclub'));
        static::assertNull($service->getProvider('missing'));
    }

    public function testDuplicateKeysThrowOnConstruction(): void
    {
        // Arrange + Act + Assert
        $this->expectException(LogicException::class);
        new PluginSettingsService([
            $this->makeProvider('clash'),
            $this->makeProvider('clash'),
        ]);
    }

    private function makeProvider(string $key, int $priority = 0): PluginSettingsProviderInterface
    {
        return new class ($key, $priority) implements PluginSettingsProviderInterface {
            public function __construct(private readonly string $key, private readonly int $priority) {}

            public function getKey(): string
            {
                return $this->key;
            }

            public function getTitleKey(): string
            {
                return 'test.title';
            }

            public function getFormType(): string
            {
                return 'TestFormType';
            }

            public function loadData(): object
            {
                return new \stdClass();
            }

            public function getFormOptions(): array
            {
                return [];
            }

            public function save(object $data, FormInterface $form): void
            {
            }

            public function getPriority(): int
            {
                return $this->priority;
            }
        };
    }
}
