<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OrganizerAdminAccessTest extends WebTestCase
{
    private const string ORGANIZER_EMAIL = 'Drew.Cano@example.org';
    private const string ORGANIZER_PASSWORD = '1234';

    public function testOrganizerCanReachDashboard(): void
    {
        $client = static::createClient();
        $this->loginAsOrganizer($client);

        $client->request('GET', '/en/admin/dashboard');

        $this->assertResponseIsSuccessful();
    }

    public function testOrganizerCanReachEventsAdmin(): void
    {
        $client = static::createClient();
        $this->loginAsOrganizer($client);

        $client->request('GET', '/en/admin/events');

        $this->assertResponseIsSuccessful();
    }

    public function testOrganizerCanReachHostsAdmin(): void
    {
        // Hosts are a prerequisite for creating an event, so organizers retain access.
        $client = static::createClient();
        $this->loginAsOrganizer($client);

        $client->request('GET', '/en/admin/hosts');

        $this->assertResponseIsSuccessful();
    }

    public function testOrganizerCanReachLocationsAdmin(): void
    {
        // Venues are a prerequisite for creating an event, so organizers retain access.
        $client = static::createClient();
        $this->loginAsOrganizer($client);

        $client->request('GET', '/en/admin/locations');

        $this->assertResponseIsSuccessful();
    }

    #[DataProvider('deniedAdminUrlsProvider')]
    public function testOrganizerIsForbiddenFromStewardOnlyUrls(string $url): void
    {
        $client = static::createClient();
        $this->loginAsOrganizer($client);

        $client->request('GET', $url);

        static::assertSame(
            403,
            $client->getResponse()->getStatusCode(),
            "Organizer must be forbidden from {$url}",
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function deniedAdminUrlsProvider(): iterable
    {
        yield 'core member admin' => ['/en/admin/member'];
        yield 'cms'               => ['/en/admin/cms'];
    }

    public function testOrganizerSidebarHidesStewardOnlyEntries(): void
    {
        $client = static::createClient();
        $this->loginAsOrganizer($client);

        $crawler = $client->request('GET', '/en/admin/dashboard');

        $this->assertResponseIsSuccessful();

        $hrefs = [];
        foreach ($crawler->filter('aside a, nav a') as $node) {
            $href = (string) $node->getAttribute('href');
            if ($href === '') {
                continue;
            }
            $path = (string) parse_url($href, PHP_URL_PATH);
            // Strip locale prefix so the comparison matches the route path defined in the controller.
            $hrefs[] = preg_replace('#^/[a-z]{2}(?=/)#', '', $path);
        }

        $forbidden = [
            '/admin/cms',
            '/admin/member',
        ];
        foreach ($forbidden as $path) {
            static::assertNotContains(
                $path,
                $hrefs,
                "Sidebar must not link to {$path} for an organizer",
            );
        }

        // Hosts and Venues are event prerequisites and must remain visible to organizers.
        foreach (['/admin/hosts', '/admin/locations'] as $path) {
            static::assertContains(
                $path,
                $hrefs,
                "Sidebar must expose {$path} to an organizer",
            );
        }
    }

    private function loginAsOrganizer(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler->selectButton('Login')->form([
            '_username' => self::ORGANIZER_EMAIL,
            '_password' => self::ORGANIZER_PASSWORD,
        ]);
        $client->submit($form);
        $client->followRedirect();
    }
}
