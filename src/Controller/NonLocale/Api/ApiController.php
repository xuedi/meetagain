<?php declare(strict_types=1);

namespace App\Controller\NonLocale\Api;

use App\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;

final class ApiController extends AbstractController
{
    private const string SPEC_RELATIVE_PATH = '/config/api/openapi.json';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    #[Route('/api/', name: 'app_api')]
    public function index(): Response
    {
        return $this->render('_non_locale/api.html.twig', [
            'sections' => $this->buildSectionsFromSpec($this->loadSpec()),
        ]);
    }

    #[Route('/api/openapi.json', name: 'app_api_openapi', methods: ['GET'])]
    public function spec(): Response
    {
        $path = $this->projectDir . self::SPEC_RELATIVE_PATH;
        if (!is_file($path)) {
            return new JsonResponse(['error' => 'Spec not found'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $response = new Response((string) file_get_contents($path), Response::HTTP_OK, [
            'Content-Type' => 'application/json',
        ]);
        // Opt out of Symfony's automatic private/no-cache downgrade; this endpoint
        // is session-agnostic and worth caching at the edge.
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $response->setPublic();
        $response->setMaxAge(300);

        return $response;
    }

    #[Route('/api/glossary', name: 'app_api_glossary', methods: ['GET'])]
    public function glossaryIndex(): Response
    {
        return new JsonResponse('glossary');
    }

    #[Route('/api/status', name: 'app_api_status', methods: ['GET'])]
    public function statusIndex(): Response
    {
        return new JsonResponse('OK');
    }

    #[Route('/api/translations', name: 'app_api_translations', methods: ['GET'])]
    public function translationsIndex(): Response
    {
        return new JsonResponse('translations');
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSpec(): array
    {
        $path = $this->projectDir . self::SPEC_RELATIVE_PATH;
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Group operations by tag (first tag wins), in the order the tags appear in the spec.
     *
     * @param array<string, mixed> $spec
     * @return array<int, array{title: string, badge: string|null, endpoints: array<int, array{method: string, apiPath: string, description: string, example: string, route: string|null}>}>
     */
    private function buildSectionsFromSpec(array $spec): array
    {
        $tagOrder = [];
        $tagMeta = [];
        foreach ($spec['tags'] ?? [] as $tag) {
            $name = $tag['name'] ?? null;
            if (!is_string($name)) {
                continue;
            }
            $tagOrder[] = $name;
            $tagMeta[$name] = [
                'title' => $tag['description'] ?? $name,
                'badge' => $tag['x-badge'] ?? null,
            ];
        }

        $endpointsByTag = array_fill_keys($tagOrder, []);
        $untagged = [];

        foreach ($spec['paths'] ?? [] as $path => $operations) {
            if (!is_array($operations)) {
                continue;
            }
            foreach ($operations as $method => $op) {
                if (!is_array($op) || !in_array(strtoupper($method), ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], true)) {
                    continue;
                }
                $entry = [
                    'method' => strtoupper($method),
                    'apiPath' => (string) $path,
                    'description' => (string) ($op['summary'] ?? $op['description'] ?? ''),
                    'example' => (string) ($op['x-example'] ?? ''),
                    'route' => null,
                ];
                $tag = $op['tags'][0] ?? null;
                if (is_string($tag) && isset($endpointsByTag[$tag])) {
                    $endpointsByTag[$tag][] = $entry;
                } else {
                    $untagged[] = $entry;
                }
            }
        }

        $sections = [];
        foreach ($tagOrder as $name) {
            if ($endpointsByTag[$name] === []) {
                continue;
            }
            $sections[] = [
                'title' => $tagMeta[$name]['title'],
                'badge' => $tagMeta[$name]['badge'],
                'endpoints' => $endpointsByTag[$name],
            ];
        }
        if ($untagged !== []) {
            $sections[] = ['title' => 'Other', 'badge' => null, 'endpoints' => $untagged];
        }

        return $sections;
    }
}
