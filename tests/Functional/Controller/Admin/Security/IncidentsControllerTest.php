<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin\Security;

use App\Entity\Incident;
use App\Enum\IncidentSeverity;
use App\Service\Security\BlockedSessionStore;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class IncidentsControllerTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';

    public function testListPageRendersForAdmin(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/security/incidents');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertSame(4, $crawler->filter('.tabs.is-boxed li')->count(), 'Four security tabs should be rendered');
    }

    public function testOldUrlProbingRouteIsGone(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/security/url-probing');

        // Assert
        static::assertSame(404, $client->getResponse()->getStatusCode());
    }

    #[DataProvider('provideRangeQueryCases')]
    public function testListAcceptsKnownAndUnknownRangeValues(string $rangeParam): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/security/incidents?range=' . $rangeParam);

        // Assert - all known and unknown values resolve to a 200 (unknown falls back to default)
        $this->assertResponseIsSuccessful();
    }

    public static function provideRangeQueryCases(): iterable
    {
        yield 'default 24h' => ['24h'];
        yield 'one week' => ['1w'];
        yield 'one month' => ['1m'];
        yield 'all time' => ['all'];
        yield 'unknown value falls back to default' => ['bogus'];
    }

    public function testListShowsClearButtonOnlyWhenIncidentsExist(): void
    {
        // Arrange - start from a clean slate (transaction-rolled-back by DAMA)
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement('DELETE FROM logs_incident');
        $this->loginAsAdmin($client);

        // Act - empty state
        $crawler = $client->request('GET', '/en/admin/security/incidents');

        // Assert - no Clear button in the topbar
        $this->assertResponseIsSuccessful();
        static::assertSame(0, $crawler->filter('.box .level-right form button:contains("Clear")')->count());

        // Arrange - plant one incident
        $this->createIncident($em);

        // Act - populated state
        $crawler = $client->request('GET', '/en/admin/security/incidents');

        // Assert - Clear button is rendered
        $this->assertResponseIsSuccessful();
        static::assertGreaterThan(0, $crawler->filter('.box .level-right a[data-post][href*="/clear"]')->count());
    }

    public function testShowReturns404ForMissingIncident(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act - any numeric id that does not exist
        $client->request('GET', '/en/admin/security/incidents/999999');

        // Assert
        static::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testShowPageHidesUnblockButtonWhenNoBlockIsActive(): void
    {
        // Arrange - the "block active" case cannot be tested here: cache.app is the
        // resettable ArrayAdapter in the test env, and Symfony's kernel.reset
        // wipes its contents between HTTP requests. Planting a block from the
        // test then issuing a GET sees a clean cache. The hides-when-empty
        // branch is what we can cover; the shows-when-blocked branch is left
        // to unit-level coverage of the controller's wiring.
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $blockStore = static::getContainer()->get(BlockedSessionStore::class);
        $blockStore->clearAll();
        $this->loginAsAdmin($client);
        $incident = $this->createIncident($em);

        // Act
        $client->request('GET', '/en/admin/security/incidents/' . $incident->getId());

        // Assert
        $this->assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        static::assertStringNotContainsString('/admin/security/incidents/' . $incident->getId() . '/unblock', $content);
    }

    public function testClearWipesAllIncidentsAndRedirectsToList(): void
    {
        // Arrange
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->createIncident($em);
        $this->createIncident($em);
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/security/incidents');
        $token = $crawler->filter('a[href*="/clear"][data-post]')->attr('data-csrf-token');
        $client->request('POST', '/en/admin/security/incidents/clear', ['_token' => $token]);

        // Assert
        $this->assertResponseRedirects('/en/admin/security/incidents');
        $em->clear();
        $remaining = $em->getRepository(Incident::class)->count([]);
        static::assertSame(0, $remaining);
    }

    public function testUnblockRouteRedirectsToShow(): void
    {
        // Arrange - we cannot meaningfully assert "block was cleared" here because
        // the in-memory cache.adapter.array used by cache.app in the test env is
        // reset between HTTP requests (Symfony kernel.reset), so a block planted
        // from the test does not survive into the controller's request scope.
        // What we can verify: the route exists, accepts the incident id, and
        // redirects to the show page.
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->loginAsAdmin($client);
        $incident = $this->createIncident($em);

        // Act
        $rawToken = 'test-csrf-unblock-' . $incident->getId();
        $session = $client->getSession();
        $session->set('_csrf/admin_security_incidents_unblock' . $incident->getId(), $rawToken);
        $session->save();
        $client->request('POST', '/en/admin/security/incidents/' . $incident->getId() . '/unblock', [
            '_token' => $rawToken,
        ]);

        // Assert
        $this->assertResponseRedirects('/en/admin/security/incidents/' . $incident->getId());
    }

    private function createIncident(EntityManagerInterface $em): Incident
    {
        $now = new DateTimeImmutable();
        $incident = new Incident();
        $incident->setIp('203.0.113.' . random_int(1, 254));
        $incident->setSessionId(bin2hex(random_bytes(13)));
        $incident->setTriggeredBy('not_found');
        $incident->setSeverity(IncidentSeverity::Medium);
        $incident->setProviderReports([]);
        $incident->setBlockedUntilDescription('12h');
        $incident->setUserAgent('Mozilla/5.0 (TestFixture)');
        $incident->setStartedAt($now);
        $incident->setEndedAt($now);
        $incident->setCreatedAt($now);
        $incident->setUpdatedAt($now);
        $em->persist($incident);
        $em->flush();

        return $incident;
    }

    private function loginAsAdmin(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler
            ->selectButton('Login')
            ->form([
                '_username' => self::ADMIN_EMAIL,
                '_password' => self::ADMIN_PASSWORD,
            ]);
        $client->submit($form);
        $client->followRedirect();
    }
}
