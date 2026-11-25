<?php declare(strict_types=1);

namespace Plugin\Fediverse\Tests\Unit\ValueObject;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Fediverse\Enum\Content;
use Plugin\Fediverse\Enum\Location;
use Plugin\Fediverse\ValueObject\Config;

class ConfigTest extends TestCase
{
    public function testARecordSurvivesTheRoundTrip(): void
    {
        // Arrange
        $config = new Config()
            ->setContent(Content::Full)
            ->setLocation(Location::Address)
            ->setImage(false)
            ->setUsername('chess_club')
            ->setAccountSummary('We play chess.')
            ->setAnnounce(false)
            ->setBlockedServers(['bad.social']);

        // Act
        $restored = Config::fromArray($config->toArray());

        // Assert
        static::assertSame($config->toArray(), $restored->toArray());
    }

    public function testAMalformedRecordFallsBackToTheDefaults(): void
    {
        // Act
        $config = Config::fromArray(['content' => 'everything', 'location' => 42, 'username' => ['x'], 'blockedServers' => 'bad.social']);

        // Assert
        static::assertSame(new Config()->toArray(), $config->toArray());
    }

    public function testBlockedServersAreLowercasedTrimmedAndUnique(): void
    {
        // Act
        $config = new Config()->setBlockedServers([' Bad.Social ', 'bad.social', '', 'spam.example']);

        // Assert
        static::assertSame(['bad.social', 'spam.example'], $config->getBlockedServers());
    }

    /** @return Generator<string, array{string, bool}> */
    public static function hosts(): Generator
    {
        yield 'the listed host' => ['bad.social', true];
        yield 'a subdomain' => ['eu.bad.social', true];
        yield 'a deeper subdomain' => ['a.b.bad.social', true];
        yield 'in capitals' => ['EU.Bad.Social', true];
        yield 'a look-alike suffix' => ['notbad.social', false];
        yield 'a parent domain' => ['social', false];
        yield 'another host' => ['good.social', false];
    }

    #[DataProvider('hosts')]
    public function testABlockCoversTheHostAndItsSubdomainsOnly(string $host, bool $blocked): void
    {
        // Arrange
        $config = new Config()->setBlockedServers(['bad.social']);

        // Act + Assert
        static::assertSame($blocked, $config->blocks($host));
    }

    public function testBlankAccountFieldsAreStoredAsNull(): void
    {
        // Act
        $config = new Config()
            ->setUsername('  ')
            ->setAccountSummary('');

        // Assert
        static::assertNull($config->getUsername());
        static::assertNull($config->getAccountSummary());
    }
}
