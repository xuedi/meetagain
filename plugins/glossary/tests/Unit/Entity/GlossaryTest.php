<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Entity;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Entity\Glossary;

class GlossaryTest extends TestCase
{
    public function testStoresAndReturnsScalarFields(): void
    {
        // Arrange
        $createdAt = new DateTimeImmutable('2026-05-01 12:00:00');
        $glossary = new Glossary();

        // Act
        $glossary->setPhrase('干嘛')->setSecondary('gàn má')->setTermLanguage('zh')->setCreatedBy(2)->setCreatedAt($createdAt);

        // Assert
        self::assertSame('干嘛', $glossary->getPhrase());
        self::assertSame('gàn má', $glossary->getSecondary());
        self::assertSame('zh', $glossary->getTermLanguage());
        self::assertSame(2, $glossary->getCreatedBy());
        self::assertSame($createdAt, $glossary->getCreatedAt());
    }

    /** @param array<string, string> $definitions */
    #[DataProvider('definitionCases')]
    public function testResolveDefinitionTriesTheRequestedLocaleThenTheSourceThenAny(array $definitions, ?string $locale, string $expected): void
    {
        // Arrange
        $glossary = new Glossary();
        foreach ($definitions as $language => $text) {
            $glossary->setDefinition($language, $text);
        }

        // Act
        $resolved = $glossary->resolveDefinition($locale, 'en');

        // Assert
        self::assertSame($expected, $resolved);
    }

    public static function definitionCases(): iterable
    {
        yield 'the requested locale wins' => [['en' => 'Hello', 'de' => 'Hallo'], 'de', 'Hallo'];
        yield 'a missing locale falls back to the source' => [['en' => 'Hello', 'de' => 'Hallo'], 'fr', 'Hello'];
        yield 'no locale falls back to the source' => [['de' => 'Hallo', 'en' => 'Hello'], null, 'Hello'];
        yield 'without the source any filled definition serves' => [['de' => 'Hallo'], 'fr', 'Hallo'];
        yield 'no definitions resolve to an empty string' => [[], 'en', ''];
    }

    public function testSettingAnEmptyDefinitionRemovesThatLanguage(): void
    {
        // Arrange
        $glossary = new Glossary()
            ->setDefinition('en', 'Hello')
            ->setDefinition('de', 'Hallo');

        // Act
        $glossary->setDefinition('de', '  ');

        // Assert
        self::assertSame(['en' => 'Hello'], $glossary->getDefinitionMap());
        self::assertCount(1, $glossary->getDefinitions());
    }

    public function testSettingALanguageTwiceUpdatesTheSameRow(): void
    {
        // Arrange
        $glossary = new Glossary()->setDefinition('en', 'Hello');
        $row = $glossary->findDefinition('en');

        // Act
        $glossary->setDefinition('en', 'Hi');

        // Assert
        self::assertSame($row, $glossary->findDefinition('en'));
        self::assertSame('Hi', $row?->getText());
        self::assertSame($glossary, $row?->getGlossary());
    }
}
