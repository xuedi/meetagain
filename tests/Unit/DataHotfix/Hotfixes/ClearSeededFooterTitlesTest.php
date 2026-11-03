<?php declare(strict_types=1);

namespace Tests\Unit\DataHotfix\Hotfixes;

use App\DataHotfix\Hotfixes\ClearSeededFooterTitles;
use App\Service\AppStateService;
use PHPUnit\Framework\TestCase;

class ClearSeededFooterTitlesTest extends TestCase
{
    public function testRemovesOnlyTheTitlesStillHoldingTheSeededEnglishDefault(): void
    {
        // Arrange
        $stored = [
            'footer_col1_title' => 'Help',
            'footer_col2_title' => 'Our Clubs',
            'footer_col3_title' => null,
            'footer_col4_title' => 'Legal',
        ];
        $removed = [];
        $appState = $this->createStub(AppStateService::class);
        $appState->method('get')->willReturnCallback(static fn(string $key): ?string => $stored[$key]);
        $appState
            ->method('remove')
            ->willReturnCallback(static function (string $key) use (&$removed): void {
                $removed[] = $key;
            });

        // Act
        new ClearSeededFooterTitles($appState)->execute();

        // Assert
        static::assertSame(['footer_col1_title', 'footer_col4_title'], $removed);
    }
}
