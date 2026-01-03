<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FilmListTest extends WebTestCase
{
    public function testFilmListRenders(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/en/films/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.title', 'Film list');
        $this->assertSelectorExists('table.table.is-fullwidth');
        $this->assertSelectorTextContains('table.table.is-fullwidth thead tr th:nth-child(1)', 'Title');
        $this->assertSelectorTextContains('table.table.is-fullwidth thead tr th:nth-child(2)', 'Year');
        $this->assertSelectorTextContains('table.table.is-fullwidth thead tr th:nth-child(3)', 'Runtime');
        $this->assertSelectorTextContains('table.table.is-fullwidth thead tr th:nth-child(4)', 'Genres');

        // Verify that at least one film from the fixture is listed
        $this->assertSelectorExists('table.table.is-fullwidth tbody tr');
        $this->assertSelectorTextContains('table.table.is-fullwidth tbody tr td:nth-child(3)', 'min');
    }
}