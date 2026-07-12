<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Entity;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Entity\Suggestion;
use Plugin\Glossary\Entity\SuggestionField;

class SuggestionTest extends TestCase
{
    public function testFromParamsExposesProperties(): void
    {
        // Arrange
        $at = new DateTimeImmutable('2026-01-01 00:00:00');

        // Act
        $suggestion = Suggestion::fromParams(createdBy: 3, createdAt: $at, field: SuggestionField::Phrase, value: '你好');

        // Assert
        self::assertSame(3, $suggestion->createdBy);
        self::assertSame($at, $suggestion->createdAt);
        self::assertSame(SuggestionField::Phrase, $suggestion->field);
        self::assertSame('你好', $suggestion->value);
    }

    public function testJsonSerializeStoresFieldAsScalar(): void
    {
        // Arrange
        $at = new DateTimeImmutable('2026-01-01 00:00:00');
        $suggestion = Suggestion::fromParams(createdBy: 3, createdAt: $at, field: SuggestionField::Category, value: '2');

        // Act
        $json = $suggestion->jsonSerialize();

        // Assert
        self::assertSame(3, $json['createdBy']);
        self::assertSame('category', $json['field']);
        self::assertSame('2', $json['value']);
        self::assertSame($at, $json['createdAt']);
    }

    public function testFromJsonReconstructsFromStoredShape(): void
    {
        // Arrange: the shape Doctrine hands back after decoding the JSON column
        $stored = [
            'createdBy' => 7,
            'createdAt' => ['date' => '2026-07-12 10:00:00.000000', 'timezone_type' => 3, 'timezone' => 'UTC'],
            'field' => 'pinyin',
            'value' => 'nǐ hǎo',
        ];

        // Act
        $suggestion = Suggestion::fromJson($stored);

        // Assert
        self::assertSame(7, $suggestion->createdBy);
        self::assertSame(SuggestionField::Pinyin, $suggestion->field);
        self::assertSame('nǐ hǎo', $suggestion->value);
        self::assertSame('2026-07-12', $suggestion->createdAt->format('Y-m-d'));
    }

    public function testGetHashIsStableForEqualDataAndDiffersOnChange(): void
    {
        // Arrange
        $at = new DateTimeImmutable('2026-01-01 00:00:00');
        $a = Suggestion::fromParams(createdBy: 3, createdAt: $at, field: SuggestionField::Phrase, value: 'x');
        $b = Suggestion::fromParams(createdBy: 3, createdAt: $at, field: SuggestionField::Phrase, value: 'x');
        $c = Suggestion::fromParams(createdBy: 3, createdAt: $at, field: SuggestionField::Phrase, value: 'y');

        // Act & Assert
        self::assertSame($a->getHash(), $b->getHash());
        self::assertNotSame($a->getHash(), $c->getHash());
    }
}
