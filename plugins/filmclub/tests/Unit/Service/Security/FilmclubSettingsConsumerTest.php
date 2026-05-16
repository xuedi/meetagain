<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Service\Security;

use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Repository\FilmclubSettingsRepository;
use Plugin\Filmclub\Service\Security\FilmclubSettingsConsumer;

class FilmclubSettingsConsumerTest extends TestCase
{
    public function testGetKeyReturnsExpectedTranslationKey(): void
    {
        // Arrange
        $consumer = new FilmclubSettingsConsumer(
            $this->createStub(FilmclubSettingsRepository::class),
        );

        // Act
        $key = $consumer->getKey();

        // Assert
        static::assertSame('filmclub_admin_secretbox.consumer_settings', $key);
    }

    public function testCountDelegatesToRepository(): void
    {
        // Arrange
        $repo = $this->createStub(FilmclubSettingsRepository::class);
        $repo->method('countWithEncryptedCredentials')->willReturn(7);

        $consumer = new FilmclubSettingsConsumer($repo);

        // Act
        $count = $consumer->count();

        // Assert
        static::assertSame(7, $count);
    }
}
