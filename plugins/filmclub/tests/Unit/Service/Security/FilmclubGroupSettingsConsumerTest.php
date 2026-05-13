<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Service\Security;

use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Repository\FilmclubGroupSettingsRepository;
use Plugin\Filmclub\Service\Security\FilmclubGroupSettingsConsumer;

class FilmclubGroupSettingsConsumerTest extends TestCase
{
    public function testGetKeyReturnsExpectedTranslationKey(): void
    {
        // Arrange
        $consumer = new FilmclubGroupSettingsConsumer(
            $this->createStub(FilmclubGroupSettingsRepository::class),
        );

        // Act
        $key = $consumer->getKey();

        // Assert
        static::assertSame('filmclub_admin_secretbox.consumer_settings', $key);
    }

    public function testCountDelegatesToRepository(): void
    {
        // Arrange
        $repo = $this->createStub(FilmclubGroupSettingsRepository::class);
        $repo->method('countWithEncryptedCredentials')->willReturn(7);

        $consumer = new FilmclubGroupSettingsConsumer($repo);

        // Act
        $count = $consumer->count();

        // Assert
        static::assertSame(7, $count);
    }
}
