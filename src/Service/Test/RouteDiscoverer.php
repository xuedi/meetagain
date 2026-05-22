<?php declare(strict_types=1);

namespace App\Service\Test;

use App\Entity\Event;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\RouterInterface;

readonly class RouteDiscoverer
{
    private const array SKIP_NAME_PATTERNS = [
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

    public function __construct(
        private RouterInterface $router,
        private EntityManagerInterface $entityManager,
    ) {}

    /** @return string[] */
    public function discoverGetUrls(): array
    {
        $params = $this->buildParamMap();

        $urls = [];
        foreach ($this->router->getRouteCollection()->all() as $name => $route) {
            if ($this->shouldSkip($name, $route->getMethods())) {
                continue;
            }

            try {
                $urls[] = $this->router->generate($name, $params);
            } catch (MissingMandatoryParametersException) {
                continue;
            } catch (Exception) {
                continue;
            }
        }

        $urls = array_values(array_unique($urls));
        sort($urls);

        return $urls;
    }

    /** @param string[] $methods */
    private function shouldSkip(string $routeName, array $methods): bool
    {
        if ($methods !== [] && !in_array('GET', $methods, true)) {
            return true;
        }

        $lower = strtolower($routeName);
        foreach (self::SKIP_NAME_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function buildParamMap(): array
    {
        $event = $this->entityManager->getRepository(Event::class)->findOneBy([]);
        $user = $this->entityManager->getRepository(User::class)->findOneBy([]);

        return [
            '_locale' => 'en',
            'id' => $event?->getId() ?? 1,
            'userId' => $user?->getId() ?? 1,
            'page' => 1,
            'year' => (int) date('Y'),
            'week' => (int) date('W'),
        ];
    }
}
