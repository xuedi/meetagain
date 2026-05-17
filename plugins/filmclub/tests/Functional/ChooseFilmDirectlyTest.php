<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ChooseFilmDirectlyTest extends WebTestCase
{
    public function testChooseDirectlyFormRequiresLogin(): void
    {
        // Arrange
        $client = static::createClient();

        // Act - anonymous access should redirect to login
        $client->request('GET', '/en/filmclub/manage/choose-directly/1');

        // Assert
        $this->assertResponseRedirects();
    }
}
