<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Functional\Admin;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FilmclubSettingsGoneTest extends WebTestCase
{
    public function testOldChooserReturnsNotFound(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/filmclub/settings');

        // Assert
        static::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testOldEditReturnsNotFound(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/filmclub/settings/1');

        // Assert
        static::assertSame(404, $client->getResponse()->getStatusCode());
    }
}
