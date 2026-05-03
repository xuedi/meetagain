<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller\NonLocale\Api;

use App\Service\Api\ApiCache;
use App\Service\Api\PluginRouteGuard;
use Plugin\Glossary\Repository\GlossaryRepository;
use Plugin\Glossary\Service\Api\GlossarySerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GlossaryApiController extends AbstractController
{
    private const string PLUGIN_KEY = 'glossary';
    private const int DEFAULT_LIMIT = 50;
    private const int MAX_LIMIT = 200;
    private const int TTL_SECONDS = 600;

    public function __construct(
        private readonly GlossaryRepository $glossaryRepository,
        private readonly GlossarySerializer $serializer,
        private readonly PluginRouteGuard $pluginRouteGuard,
        private readonly ApiCache $apiCache,
    ) {}

    #[Route('/api/glossary', name: 'plugin_glossary_api_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->pluginRouteGuard->requireActive(self::PLUGIN_KEY);

        $limit = $this->clamp((int) $request->query->get('limit', (string) self::DEFAULT_LIMIT), 1, self::MAX_LIMIT);
        $offset = max(0, (int) $request->query->get('offset', '0'));
        $categorySlug = trim((string) $request->query->get('category', ''));

        $payload = $this->apiCache->getJson(
            $request,
            'glossary.list',
            self::TTL_SECONDS,
            ['limit' => $limit, 'offset' => $offset, 'category' => $categorySlug],
            function () use ($limit, $offset, $categorySlug): array {
                $criteria = ['approved' => true];
                $category = $categorySlug !== '' ? $this->serializer->categoryFromSlug($categorySlug) : null;
                if ($category !== null) {
                    $criteria['category'] = $category;
                }

                $entries = $this->glossaryRepository->findBy(
                    $criteria,
                    ['createdAt' => 'DESC'],
                    $limit,
                    $offset,
                );
                $total = $this->glossaryRepository->count($criteria);

                return [
                    'items' => array_map(fn($entry) => $this->serializer->toArray($entry), $entries),
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                ];
            },
        );

        return $this->jsonWithCaching($payload);
    }

    #[Route('/api/glossary/{id}', name: 'plugin_glossary_api_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id, Request $request): JsonResponse
    {
        $this->pluginRouteGuard->requireActive(self::PLUGIN_KEY);

        $payload = $this->apiCache->getJson(
            $request,
            'glossary.detail',
            self::TTL_SECONDS,
            ['id' => $id],
            function () use ($id): ?array {
                $entry = $this->glossaryRepository->find($id);
                if ($entry === null || !$entry->getApproved()) {
                    return null;
                }

                return $this->serializer->toArray($entry);
            },
        );

        if ($payload === null) {
            return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->jsonWithCaching($payload);
    }

    private function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function jsonWithCaching(array $data): JsonResponse
    {
        $response = new JsonResponse($data);
        $response->headers->set('Cache-Control', 'public, max-age=60');

        return $response;
    }
}
