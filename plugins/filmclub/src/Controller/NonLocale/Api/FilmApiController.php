<?php declare(strict_types=1);

namespace Plugin\Filmclub\Controller\NonLocale\Api;

use App\Service\Api\ApiCache;
use App\Service\Api\PluginRouteGuard;
use Plugin\Filmclub\Repository\FilmRepository;
use Plugin\Filmclub\Repository\VoteRepository;
use Plugin\Filmclub\Service\Api\FilmSerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FilmApiController extends AbstractController
{
    private const string PLUGIN_KEY = 'filmclub';
    private const int DEFAULT_LIMIT = 50;
    private const int MAX_LIMIT = 200;
    private const int TTL_SECONDS = 120;

    public function __construct(
        private readonly FilmRepository $filmRepository,
        private readonly VoteRepository $voteRepository,
        private readonly FilmSerializer $serializer,
        private readonly PluginRouteGuard $pluginRouteGuard,
        private readonly ApiCache $apiCache,
    ) {}

    #[Route('/api/films', name: 'plugin_filmclub_api_films_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->pluginRouteGuard->requireActive(self::PLUGIN_KEY);

        $limit = $this->clamp((int) $request->query->get('limit', (string) self::DEFAULT_LIMIT), 1, self::MAX_LIMIT);
        $offset = max(0, (int) $request->query->get('offset', '0'));

        $payload = $this->apiCache->getJson(
            $request,
            'filmclub.films.list',
            self::TTL_SECONDS,
            ['limit' => $limit, 'offset' => $offset],
            function () use ($limit, $offset): array {
                $films = $this->filmRepository->findBy([], ['createdAt' => 'DESC'], $limit, $offset);
                $total = $this->filmRepository->count([]);

                return [
                    'items' => array_map(fn($film) => $this->serializer->toSummary($film), $films),
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                ];
            },
        );

        return $this->jsonWithCaching($payload);
    }

    #[Route('/api/films/{id}', name: 'plugin_filmclub_api_films_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id, Request $request): JsonResponse
    {
        $this->pluginRouteGuard->requireActive(self::PLUGIN_KEY);

        $payload = $this->apiCache->getJson(
            $request,
            'filmclub.films.detail',
            self::TTL_SECONDS,
            ['id' => $id],
            function () use ($id): ?array {
                $film = $this->filmRepository->find($id);
                if ($film === null) {
                    return null;
                }
                $vote = $this->findLatestVoteForFilm($id);

                return $this->serializer->toDetail($film, $vote);
            },
        );

        if ($payload === null) {
            return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->jsonWithCaching($payload);
    }

    private function findLatestVoteForFilm(int $filmId): ?\Plugin\Filmclub\Entity\Vote
    {
        $closed = $this->voteRepository->findClosedVotes();
        foreach ($closed as $vote) {
            foreach ($vote->getBallots() as $ballot) {
                if ($ballot->getFilm()?->getId() === $filmId) {
                    return $vote;
                }
            }
        }

        return null;
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
