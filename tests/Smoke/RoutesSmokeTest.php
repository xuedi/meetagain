<?php

declare(strict_types=1);

namespace Tests\Smoke;

use App\Entity\User;
use App\Service\Test\RouteDiscoverer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Router-driven smoke test.
 *
 * Discovers every GET route registered in Symfony (core + all plugins) via
 * the shared RouteDiscoverer service, generates a URL using its parameter
 * map, and asserts no 5xx response.
 */
class RoutesSmokeTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';

    /** @var string[]|null */
    private static ?array $allUrls = null;

    /** @return iterable<string, array{string}> */
    public static function allUrlProvider(): iterable
    {
        foreach (self::resolveUrls() as $url) {
            yield $url => [$url];
        }
    }

    /** @return iterable<string, array{string}> */
    public static function adminUrlProvider(): iterable
    {
        foreach (self::resolveUrls() as $url) {
            if (!str_contains($url, '/admin/')) {
                continue;
            }

            yield $url => [$url];
        }
    }

    #[DataProvider('allUrlProvider')]
    public function testPublicRouteDoesNotReturn5xx(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        $status = $client->getResponse()->getStatusCode();
        self::assertLessThan(500, $status, "Route {$url} returned HTTP {$status}");
    }

    #[DataProvider('adminUrlProvider')]
    public function testAdminRouteDoesNotReturn5xxAsAdmin(string $url): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $client->request('GET', $url);

        $status = $client->getResponse()->getStatusCode();
        self::assertLessThan(500, $status, "Admin route {$url} returned HTTP {$status} for authenticated admin");
    }

    /** @return string[] */
    private static function resolveUrls(): array
    {
        if (self::$allUrls !== null) {
            return self::$allUrls;
        }

        static::bootKernel();
        $container = static::getContainer();

        /** @var RouteDiscoverer $discoverer */
        $discoverer = $container->get(RouteDiscoverer::class);

        $urls = $discoverer->discoverGetUrls();

        static::ensureKernelShutdown();

        self::$allUrls = $urls;

        return self::$allUrls;
    }

    private function loginAsAdmin(KernelBrowser $client): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = $em->getRepository(User::class)->findOneBy(['email' => self::ADMIN_EMAIL]);

        if ($admin === null) {
            self::fail('Admin user not found in test fixtures (' . self::ADMIN_EMAIL . ')');
        }

        $client->loginUser($admin);
    }
}
