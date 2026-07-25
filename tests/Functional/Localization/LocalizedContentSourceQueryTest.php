<?php declare(strict_types=1);

namespace App\Tests\Functional\Localization;

use App\Localization\LocalizedContentSourceRegistry;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Every registered source builds its own DQL from field names that only exist at runtime, so a
 * renamed property surfaces as a query error rather than a static failure.
 */
class LocalizedContentSourceQueryTest extends WebTestCase
{
    public function testEverySourceCanExecuteItsQueries(): void
    {
        // Arrange
        self::bootKernel();
        $sources = self::getContainer()->get(LocalizedContentSourceRegistry::class)->all();
        self::assertNotEmpty($sources, 'At least the core sources must be registered.');
        $ownerIds = range(1, 20);
        $keepLocales = ['en'];

        foreach ($sources as $source) {
            // Act
            $count = $source->countOutsideLocales($ownerIds, $keepLocales);
            $rows = $source->findOutsideLocales($ownerIds, $keepLocales);

            // Assert
            self::assertGreaterThanOrEqual(0, $count, $source->getKey());
            self::assertCount($count, $rows, $source->getKey() . ' must report the same set it lists.');

            foreach ($rows as $row) {
                self::assertSame($source->getKey(), $row->sourceKey);
                self::assertNotContains($row->locale, $keepLocales);
            }
        }
    }

    public function testEmptyArgumentsNeverTouchContent(): void
    {
        // Arrange
        self::bootKernel();
        $sources = self::getContainer()->get(LocalizedContentSourceRegistry::class)->all();

        foreach ($sources as $source) {
            // Act & Assert
            self::assertSame(0, $source->countOutsideLocales([], ['en']), $source->getKey());
            self::assertSame(0, $source->countOutsideLocales([1], []), $source->getKey());
            self::assertSame(0, $source->deleteOutsideLocales([], ['en']), $source->getKey());
            self::assertSame(0, $source->deleteOutsideLocales([1], []), $source->getKey());
        }
    }
}
