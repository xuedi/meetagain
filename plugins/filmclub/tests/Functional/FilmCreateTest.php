<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FilmCreateTest extends WebTestCase
{
    private const ADMIN_EMAIL = 'Admin@example.org';
    private const ADMIN_PASSWORD = '1234';

    public function testCreateFilm(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $crawler = $client->request('GET', '/en/films/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save')->form([
            'film[title]' => 'Test Film',
            'film[year]' => 2024,
            'film[runtime]' => 120,
            'film[genres]' => ['action'],
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/en/films/');
        $client->followRedirect();

        $this->assertSelectorTextContains('table', 'Test Film');
    }

    private function loginAsAdmin(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler->selectButton('Login')->form([
            '_username' => self::ADMIN_EMAIL,
            '_password' => self::ADMIN_PASSWORD,
        ]);
        $client->submit($form);
        $client->followRedirect();
    }
}
