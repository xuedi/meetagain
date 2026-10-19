<?php declare(strict_types=1);

namespace Tests\Unit\DataHotfix\Hotfixes;

use App\DataHotfix\Hotfixes\RemoveCronThrottleKeys;
use App\Service\AppStateService;
use PHPUnit\Framework\TestCase;

class RemoveCronThrottleKeysTest extends TestCase
{
    public function testRemovesTheCirculationThrottleKey(): void
    {
        // Arrange
        $removed = [];
        $appState = $this->createStub(AppStateService::class);
        $appState
            ->method('remove')
            ->willReturnCallback(static function (string $key) use (&$removed): void {
                $removed[] = $key;
            });

        // Act
        new RemoveCronThrottleKeys($appState)->execute();

        // Assert
        static::assertSame(['circulation.maintenance_last_run'], $removed);
    }
}
