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
            $methods = $route->getMethods();
            if ($methods !== [] && !in_array('GET', $methods, true)) {
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
