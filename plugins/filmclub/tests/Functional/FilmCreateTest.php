<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Full film creation flow is covered in FilmSuggestApproveTest (Phase 15 rewrite).
 */
class FilmCreateTest extends WebTestCase
{
    public function testFilmListIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/en/films/');

        $this->assertResponseIsSuccessful();
    }
}
