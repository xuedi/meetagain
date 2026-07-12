<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Entity;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Entity\Category;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Entity\SuggestionField;
use ReflectionProperty;

class GlossaryTest extends TestCase
{
    public function testStoresAndReturnsScalarFields(): void
    {
        // Arrange
        $createdAt = new DateTimeImmutable('2026-05-01 12:00:00');
        $glossary = new Glossary();

        // Act
        $glossary->setPhrase('干嘛')
            ->setPinyin('gàn má')
            ->setExplanation('how is it going?')
            ->setCategory(Category::Greeting)
            ->setApproved(true)
            ->setCreatedBy(2)
            ->setCreatedAt($createdAt);

        // Assert
        self::assertSame('干嘛', $glossary->getPhrase());
        self::assertSame('gàn má', $glossary->getPinyin());
        self::assertSame('how is it going?', $glossary->getExplanation());
        self::assertSame(Category::Greeting, $glossary->getCategory());
        self::assertTrue($glossary->getApproved());
        self::assertSame(2, $glossary->getCreatedBy());
        self::assertSame($createdAt, $glossary->getCreatedAt());
    }

    public function testGetSuggestionsReturnsEmptyForFreshEntity(): void
    {
        // Arrange: a brand-new entity has a null suggestion column (regression: must not warn on foreach)
        $glossary = new Glossary();

        // Act
        $suggestions = $glossary->getSuggestions();

        // Assert
        self::assertSame([], $suggestions);
    }

    public function testGetExplanationShortenedWrapsLongText(): void
    {
        // Arrange
        $glossary = new Glossary();
        $glossary->setExplanation(str_repeat('word ', 40));

        // Act
        $wrapped = $glossary->getExplanationShortened(60);

        // Assert
        self::assertStringContainsString("\n", $wrapped);
    }

    public function testStoredSuggestionsAreReconstructed(): void
    {
        // Arrange
        $glossary = new Glossary();
        $this->injectStoredSuggestions($glossary, [
            $this->storedSuggestion(1, 'phrase', 'A'),
            $this->storedSuggestion(2, 'pinyin', 'B'),
        ]);

        // Act
        $suggestions = $glossary->getSuggestions();

        // Assert
        self::assertCount(2, $suggestions);
        self::assertSame(SuggestionField::Phrase, $suggestions[0]->field);
        self::assertSame('A', $suggestions[0]->value);
        self::assertSame(SuggestionField::Pinyin, $suggestions[1]->field);
    }

    public function testGetSuggestionFindsByHash(): void
    {
        // Arrange
        $glossary = new Glossary();
        $this->injectStoredSuggestions($glossary, [$this->storedSuggestion(1, 'phrase', 'A')]);
        $hash = $glossary->getSuggestions()[0]->getHash();

        // Act
        $found = $glossary->getSuggestion($hash);

        // Assert
        self::assertSame('A', $found->value);
    }

    public function testGetSuggestionThrowsWhenHashUnknown(): void
    {
        // Arrange
        $glossary = new Glossary();
        $this->injectStoredSuggestions($glossary, [$this->storedSuggestion(1, 'phrase', 'A')]);

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $glossary->getSuggestion('does-not-exist');
    }

    public function testRemoveSuggestionReturnsRemainingCount(): void
    {
        // Arrange
        $glossary = new Glossary();
        $this->injectStoredSuggestions($glossary, [
            $this->storedSuggestion(1, 'phrase', 'A'),
            $this->storedSuggestion(2, 'pinyin', 'B'),
        ]);
        $hash = $glossary->getSuggestions()[0]->getHash();

        // Act
        $remaining = $glossary->removeSuggestion($hash);

        // Assert
        self::assertSame(1, $remaining);
    }

    /**
     * @return array{createdBy: int, createdAt: array{date: string, timezone_type: int, timezone: string}, field: string, value: string}
     */
    private function storedSuggestion(int $createdBy, string $field, string $value): array
    {
        return [
            'createdBy' => $createdBy,
            'createdAt' => ['date' => '2026-07-12 10:00:00.000000', 'timezone_type' => 3, 'timezone' => 'UTC'],
            'field' => $field,
            'value' => $value,
        ];
    }

    /**
     * @param list<array<string, mixed>> $suggestions
     */
    private function injectStoredSuggestions(Glossary $glossary, array $suggestions): void
    {
        $property = new ReflectionProperty(Glossary::class, 'suggestion');
        $property->setValue($glossary, $suggestions);
    }
}
