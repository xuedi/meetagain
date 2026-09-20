<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Enum\AnswerMode;
use Plugin\Glossary\Enum\Direction;
use Plugin\Glossary\ValueObject\Config;

class ConfigTest extends TestCase
{
    public function testNeutralDefaultIsTermAndDefinitionOnlyWithTheTrainerOff(): void
    {
        // Arrange + Act
        $config = new Config();

        // Assert
        static::assertFalse($config->isSecondaryEnabled());
        static::assertNull($config->getPrimaryLabel());
        static::assertNull($config->getSecondaryLabel());
        static::assertNull($config->getTermLanguage());
        static::assertFalse($config->isTrainerEnabled());
        static::assertFalse($config->isLeaderboardEnabled());
        static::assertSame([Direction::TermToDefinition, Direction::DefinitionToTerm], $config->getDirections());
        static::assertSame(AnswerMode::Flip, $config->getDefaultAnswerMode());
    }

    public function testToArrayFromArrayRoundTrip(): void
    {
        // Arrange
        $config = new Config()
            ->setSecondaryEnabled(true)
            ->setSecondaryLabel('Pinyin')
            ->setPrimaryLabel('Word')
            ->setDefinitionLabel('Meaning')
            ->setTermLanguage(' ZH ')
            ->setTrainerEnabled(true)
            ->setSessionSize(30)
            ->setNewCardsPerDay(15)
            ->setDirections([Direction::SecondaryToTerm])
            ->setDefaultAnswerMode(AnswerMode::Typing)
            ->setLeaderboardEnabled(true);

        // Act
        $restored = Config::fromArray($config->toArray());

        // Assert
        static::assertTrue($restored->isSecondaryEnabled());
        static::assertSame('Pinyin', $restored->getSecondaryLabel());
        static::assertSame('Word', $restored->getPrimaryLabel());
        static::assertSame('Meaning', $restored->getDefinitionLabel());
        static::assertSame('zh', $restored->getTermLanguage());
        static::assertTrue($restored->isTrainerEnabled());
        static::assertSame(30, $restored->getSessionSize());
        static::assertSame(15, $restored->getNewCardsPerDay());
        static::assertSame([Direction::SecondaryToTerm], $restored->getDirections());
        static::assertSame(AnswerMode::Typing, $restored->getDefaultAnswerMode());
        static::assertTrue($restored->isLeaderboardEnabled());
    }

    public function testSizesAreClampedToTheirLimits(): void
    {
        // Arrange + Act
        $config = new Config()
            ->setSessionSize(1000)
            ->setNewCardsPerDay(-5);
        $stored = Config::fromArray(['sessionSize' => 1]);

        // Assert
        static::assertSame(Config::SESSION_SIZE_MAX, $config->getSessionSize());
        static::assertSame(0, $config->getNewCardsPerDay());
        static::assertSame(Config::SESSION_SIZE_MIN, $stored->getSessionSize());
    }

    public function testTheSecondaryDirectionIsOnlyOfferedWithTheSecondaryField(): void
    {
        // Arrange
        $directions = [Direction::TermToDefinition, Direction::SecondaryToTerm];
        $without = new Config()->setDirections($directions);
        $with = new Config()
            ->setDirections($directions)
            ->setSecondaryEnabled(true);
        $onlySecondary = new Config()->setDirections([Direction::SecondaryToTerm]);

        // Act & Assert
        static::assertSame([Direction::TermToDefinition], $without->getOfferedDirections());
        static::assertSame($directions, $with->getOfferedDirections());
        static::assertSame([Direction::TermToDefinition], $onlySecondary->getOfferedDirections());
    }

    public function testUnknownStoredValuesFallBackToDefaults(): void
    {
        // Arrange + Act
        $config = Config::fromArray(['directions' => ['bogus'], 'defaultAnswerMode' => 'telepathy']);

        // Assert
        static::assertSame([Direction::TermToDefinition], $config->getDirections());
        static::assertSame(AnswerMode::Flip, $config->getDefaultAnswerMode());
    }
}
