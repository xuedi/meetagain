<?php declare(strict_types=1);

namespace Tests\Unit\Item\Taxonomy;

use App\Item\Taxonomy\Axis;
use App\Item\Taxonomy\Config;
use App\Item\Taxonomy\SuggestionBuilder;
use App\Service\Config\LanguageService;
use PHPUnit\Framework\TestCase;

class SuggestionBuilderTest extends TestCase
{
    public function testRowsFallBackToTheSourceLocale(): void
    {
        // Arrange
        $taxonomy = (new Config())->setCategories([
            ['id' => 1, 'labels' => ['en' => 'Greeting', 'de' => 'Gruss']],
            ['id' => 2, 'labels' => ['en' => 'Slang']],
        ]);

        // Act
        $rows = $this->builder()->rows($taxonomy, Axis::Category, 'de');

        // Assert
        static::assertSame([1 => 'Gruss', 2 => 'Slang'], $rows);
    }

    public function testAnEditedLabelBecomesARename(): void
    {
        // Arrange
        $taxonomy = (new Config())->setCategories([['id' => 1, 'labels' => ['en' => 'Greeting']]]);

        // Act
        $changes = $this->builder()->changes($taxonomy, Axis::Category, 'en', [1 => 'Salutation'], []);

        // Assert
        static::assertCount(1, $changes);
        static::assertSame('category_rename_1_en', $changes[0]->field);
        static::assertSame('Greeting', $changes[0]->before);
        static::assertSame('Salutation', $changes[0]->after);
    }

    public function testAClearedLabelBecomesARemoval(): void
    {
        // Arrange
        $taxonomy = (new Config())->setTags([['id' => 4, 'labels' => ['en' => 'Formal']]]);

        // Act
        $changes = $this->builder()->changes($taxonomy, Axis::Tag, 'en', [4 => '  '], []);

        // Assert
        static::assertCount(1, $changes);
        static::assertSame('tag_remove_4', $changes[0]->field);
        static::assertSame('Formal', $changes[0]->before);
        static::assertNull($changes[0]->after);
    }

    public function testAnUntouchedLabelProposesNothing(): void
    {
        // Arrange
        $taxonomy = (new Config())->setCategories([['id' => 1, 'labels' => ['en' => 'Greeting']]]);

        // Act
        $changes = $this->builder()->changes($taxonomy, Axis::Category, 'en', [1 => 'Greeting'], ['', '   ']);

        // Assert
        static::assertSame([], $changes);
    }

    public function testFilledAddRowsAreNumberedFromZero(): void
    {
        // Arrange
        $taxonomy = new Config();

        // Act
        $changes = $this->builder()->changes($taxonomy, Axis::Category, 'de', [], ['Gruss', '', 'Slang']);

        // Assert
        static::assertSame(['category_add_de_0', 'category_add_de_1'], array_column($changes, 'field'));
        static::assertSame([null, null], array_column($changes, 'before'));
        static::assertSame(['Gruss', 'Slang'], array_column($changes, 'after'));
    }

    public function testAnEditOfADefinitionOnlyLabelledElsewhereWritesIntoTheProposerLocale(): void
    {
        // Arrange
        $taxonomy = (new Config())->setCategories([['id' => 5, 'labels' => ['en' => 'Slang']]]);

        // Act
        $changes = $this->builder()->changes($taxonomy, Axis::Category, 'de', [5 => 'Umgangssprache'], []);

        // Assert
        static::assertSame('category_rename_5_de', $changes[0]->field);
        static::assertSame('Slang', $changes[0]->before);
    }

    private function builder(): SuggestionBuilder
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getFilteredDefaultLocale')->willReturn('en');

        return new SuggestionBuilder($languageService);
    }
}
