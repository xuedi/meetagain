<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Controller\NonLocale\Api;

use App\Service\Api\ApiCache;
use App\Service\Api\PluginRouteGuard;
use App\Service\Config\LanguageService;
use Plugin\Dinnerclub\Repository\DinnerRepository;
use Plugin\Dinnerclub\Service\Api\DinnerSerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DinnerApiController extends AbstractController
{
    private const string PLUGIN_KEY = 'dinnerclub';
    private const int DEFAULT_LIMIT = 20;
    private const int MAX_LIMIT = 100;
    private const int TTL_SECONDS = 120;

    public function __construct(
        private readonly DinnerRepository $dinnerRepository,
        private readonly DinnerSerializer $serializer,
        private readonly LanguageService $languageService,
        private readonly PluginRouteGuard $pluginRouteGuard,
        private readonly ApiCache $apiCache,
    ) {}

    #[Route('/api/dinners', name: 'plugin_dinnerclub_api_dinners_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->pluginRouteGuard->requireActive(self::PLUGIN_KEY);

        $limit = $this->clamp((int) $request->query->get('limit', (string) self::DEFAULT_LIMIT), 1, self::MAX_LIMIT);
        $offset = max(0, (int) $request->query->get('offset', '0'));

        $payload = $this->apiCache->getJson(
            $request,
            'dinnerclub.dinners.list',
            self::TTL_SECONDS,
            ['limit' => $limit, 'offset' => $offset],
            function () use ($limit, $offset): array {
                $dinners = $this->dinnerRepository->findBy([], ['createdAt' => 'DESC'], $limit, $offset);
                $total = $this->dinnerRepository->count([]);

                return [
                    'items' => array_map(fn($dinner) => $this->serializer->toSummary($dinner), $dinners),
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                ];
            },
        );

        return $this->jsonWithCaching($payload);
    }

    #[Route('/api/dinners/{id}', name: 'plugin_dinnerclub_api_dinners_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id, Request $request): JsonResponse
    {
        $this->pluginRouteGuard->requireActive(self::PLUGIN_KEY);

        $locale = $this->resolveLocale($request);
        $baseUrl = $request->getSchemeAndHttpHost();

        $payload = $this->apiCache->getJson(
            $request,
            'dinnerclub.dinners.detail',
            self::TTL_SECONDS,
            ['id' => $id, 'locale' => $locale, 'baseUrl' => $baseUrl],
            function () use ($id, $locale, $baseUrl): ?array {
                $dinner = $this->dinnerRepository->find($id);
                if ($dinner === null) {
                    return null;
                }

                return $this->serializer->toDetail($dinner, $locale, $baseUrl);
            },
        );

        if ($payload === null) {
            return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->jsonWithCaching($payload);
    }

    private function resolveLocale(Request $request): string
    {
        $query = $request->query->get('locale', '');
        if ($query !== '' && $this->languageService->isValidCode($query)) {
            return $query;
        }
        $requestLocale = $request->getLocale();
        if ($requestLocale !== '' && $this->languageService->isValidCode($requestLocale)) {
            return $requestLocale;
        }

        return $this->languageService->getFilteredDefaultLocale();
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
