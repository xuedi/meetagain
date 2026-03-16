<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Controller\AbstractController;
use App\Service\LogReaderService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api'), IsGranted('ROLE_ADMIN')]
class LogsApiController extends AbstractController
{
    public function __construct(
        private readonly LogReaderService $logReaderService,
    ) {}

    #[Route('/logs', name: 'app_api_logs', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $limit   = $request->query->getInt('limit', 100);
        $level   = $request->query->get('level');
        $channel = $request->query->get('channel');

        $entries = $this->logReaderService->getRecentEntries($limit, $level, $channel);

        return new JsonResponse([
            'count'   => count($entries),
            'file'    => basename($this->logReaderService->getLogFilePath()),
            'entries' => array_map(static fn($e) => $e->toArray(), $entries),
        ]);
    }
}
