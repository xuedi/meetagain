<?php declare(strict_types=1);

namespace Tests\Smoke;

use App\Entity\Event;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\RouterInterface;

/**
 * Router-driven smoke test.
 *
 * Discovers every GET route registered in Symfony (core + all plugins),
 * generates a URL using a shared parameter map, and asserts no 5xx response.
 * Routes that need parameters not in the map are silently skipped.
 * New plugin routes are covered automatically without any changes here.
 */
class RoutesSmokeTest extends WebTestCase
{
    private const ADMIN_EMAIL = 'Admin@example.org';
    private const ADMIN_PASSWORD = '1234';

    /**
     * Route name substrings that indicate side-effecting GET endpoints.
     * Method filtering already excludes POST/DELETE, but some GET routes
     * still mutate state (ordering, deletion) or have other side effects.
     */
    private const SKIP_NAME_PATTERNS = [
        'logout',
        '_wdt',
        '_profiler',
        '_error',
        'app_install',
        'delete',
        '_up',
        '_down',
        'remove',
        'toggle',
        'resend',
        'verify',
    ];

    /** @var string[]|null */
    private static ?array $allUrls = null;

    // -------------------------------------------------------------------------
    // Data providers
    // -------------------------------------------------------------------------

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
            if (str_contains($url, '/admin/')) {
                yield $url => [$url];
            }
        }
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    #[DataProvider('allUrlProvider')]
    public function testPublicRouteDoesNotReturn5xx(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        $status = $client->getResponse()->getStatusCode();
        self::assertLessThan(500, $status, "Route $url returned HTTP $status");
    }

    #[DataProvider('adminUrlProvider')]
    public function testAdminRouteDoesNotReturn5xxAsAdmin(string $url): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $client->request('GET', $url);

        $status = $client->getResponse()->getStatusCode();
        self::assertLessThan(500, $status, "Admin route $url returned HTTP $status for authenticated admin");
    }

    // -------------------------------------------------------------------------
    // URL collection (runs once before any test via data provider)
    // -------------------------------------------------------------------------

    /** @return string[] */
    private static function resolveUrls(): array
    {
        if (self::$allUrls !== null) {
            return self::$allUrls;
        }

        static::bootKernel();
        $container = static::getContainer();

        /** @var RouterInterface $router */
        $router = $container->get(RouterInterface::class);
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $params = self::buildParamMap($em);

        $urls = [];
        foreach ($router->getRouteCollection()->all() as $name => $route) {
            if (self::shouldSkip($name, $route->getMethods())) {
                continue;
            }

            try {
                $urls[] = $router->generate($name, $params);
            } catch (MissingMandatoryParametersException) {
                // Route needs a parameter not in our map — silently skip
            } catch (\Exception) {
                // Any other generation failure — skip
            }
        }

        static::ensureKernelShutdown();

        self::$allUrls = array_values(array_unique($urls));

        return self::$allUrls;
    }

    /** @param string[] $methods */
    private static function shouldSkip(string $routeName, array $methods): bool
    {
        // Skip non-GET routes
        if ($methods !== [] && !in_array('GET', $methods, true)) {
            return true;
        }

        // Skip routes with side effects on GET
        $lower = strtolower($routeName);
        foreach (self::SKIP_NAME_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private static function buildParamMap(EntityManagerInterface $em): array
    {
        $event = $em->getRepository(Event::class)->findOneBy([]);
        $user = $em->getRepository(User::class)->findOneBy([]);

        return [
            '_locale' => 'en',
            'id'      => $event?->getId() ?? 1,
            'userId'  => $user?->getId() ?? 1,
            'page'    => 1,
            'year'    => (int) date('Y'),
            'week'    => (int) date('W'),
        ];
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
