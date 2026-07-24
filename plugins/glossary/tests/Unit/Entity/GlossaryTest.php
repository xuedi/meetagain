<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Entity;

use DateTimeImmutable;
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
        $glossary->setPhrase('干嘛')
            ->setPinyin('gàn má')
            ->setExplanation('how is it going?')
            ->setApproved(true)
            ->setCreatedBy(2)
            ->setCreatedAt($createdAt);

        // Assert
        self::assertSame('干嘛', $glossary->getPhrase());
        self::assertSame('gàn má', $glossary->getPinyin());
        self::assertSame('how is it going?', $glossary->getExplanation());
        self::assertTrue($glossary->getApproved());
        self::assertSame(2, $glossary->getCreatedBy());
        self::assertSame($createdAt, $glossary->getCreatedAt());
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
}
