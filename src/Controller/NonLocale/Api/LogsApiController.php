<?php declare(strict_types=1);

namespace App\Controller\NonLocale\Api;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use App\Entity\Activity;
use App\Entity\CronLog;
use App\Entity\NotFoundLog;
use App\Repository\ActivityRepository;
use App\Repository\CronLogRepository;
use App\Repository\NotFoundLogRepository;
use App\Service\System\SystemLogService;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/logs'), IsGranted('ROLE_ADMIN')]
final class LogsApiController extends AbstractController
{
    private const int SYSTEM_DEFAULT_LIMIT = 100;
    private const int SYSTEM_MAX_LIMIT = SystemLogService::MAX_LIMIT;
    private const int ACTIVITY_DEFAULT_LIMIT = 100;
    private const int ACTIVITY_MAX_LIMIT = 1000;
    private const int NOT_FOUND_DEFAULT_LIMIT = 200;
    private const int NOT_FOUND_MAX_LIMIT = 1000;
    private const int CRON_DEFAULT_LIMIT = 200;
    private const int CRON_MAX_LIMIT = 5000;

    public function __construct(
        private readonly SystemLogService $systemLogService,
        private readonly ActivityService $activityService,
        private readonly ActivityRepository $activityRepository,
        private readonly NotFoundLogRepository $notFoundLogRepository,
        private readonly CronLogRepository $cronLogRepository,
    ) {}

    #[Route('', name: 'app_api_logs_summary', methods: ['GET'])]
    public function summary(): JsonResponse
    {
        return new JsonResponse([
            'system' => [
                'count' => $this->systemLogService->countLines(),
                'latest' => $this->formatTimestamp($this->systemLogService->getLatestTimestamp()),
            ],
            'activity' => [
                'count' => $this->activityRepository->countAll(),
                'latest' => $this->formatTimestamp($this->activityRepository->findMostRecent()?->getCreatedAt()),
            ],
            'notFound' => [
                'count' => $this->notFoundLogRepository->countAll(),
                'latest' => $this->formatTimestamp($this->notFoundLogRepository->findMostRecent()?->getCreatedAt()),
            ],
            'cron' => [
                'count' => $this->cronLogRepository->countAll(),
                'latest' => $this->formatTimestamp($this->cronLogRepository->findMostRecent()?->getRunAt()),
            ],
        ]);
    }

    #[Route('/system', name: 'app_api_logs_system', methods: ['GET'])]
    public function system(Request $request): JsonResponse
    {
        $limit = $this->resolveLimit($request, self::SYSTEM_DEFAULT_LIMIT, self::SYSTEM_MAX_LIMIT);
        $level = $request->query->get('level');
        $channel = $request->query->get('channel');

        $entries = $this->systemLogService->getRecentEntries($limit, $level, $channel);

        return new JsonResponse([
            'count' => count($entries),
            'limit' => $limit,
            'file' => basename($this->systemLogService->getLogFilePath()),
            'entries' => array_map(static fn($e) => $e->toArray(), $entries),
        ]);
    }

    #[Route('/activity', name: 'app_api_logs_activity', methods: ['GET'])]
    public function activity(Request $request): JsonResponse
    {
        $limit = $this->resolveLimit($request, self::ACTIVITY_DEFAULT_LIMIT, self::ACTIVITY_MAX_LIMIT);
        $entries = $this->activityService->getAdminList($limit);

        return new JsonResponse([
            'count' => count($entries),
            'limit' => $limit,
            'entries' => array_map(static fn(Activity $a) => [
                'id' => $a->getId(),
                'createdAt' => $a->getCreatedAt()?->format('c'),
                'type' => $a->getType(),
                'message' => $a->getMessage(),
                'user' => $a->getUser() === null ? null : [
                    'id' => $a->getUser()->getId(),
                    'name' => $a->getUser()->getName(),
                ],
                'meta' => $a->getMeta() ?? [],
            ], $entries),
        ]);
    }

    #[Route('/not-found', name: 'app_api_logs_not_found', methods: ['GET'])]
    public function notFound(Request $request): JsonResponse
    {
        $limit = $this->resolveLimit($request, self::NOT_FOUND_DEFAULT_LIMIT, self::NOT_FOUND_MAX_LIMIT);
        $entries = $this->notFoundLogRepository->getRecent($limit);

        return new JsonResponse([
            'count' => count($entries),
            'limit' => $limit,
            'entries' => array_map(static fn(NotFoundLog $n) => [
                'id' => $n->getId(),
                'createdAt' => $n->getCreatedAt()?->format('c'),
                'url' => $n->getUrl(),
                'ip' => $n->getIp(),
            ], $entries),
        ]);
    }

    #[Route('/cron', name: 'app_api_logs_cron', methods: ['GET'])]
    public function cron(Request $request): JsonResponse
    {
        $limit = $this->resolveLimit($request, self::CRON_DEFAULT_LIMIT, self::CRON_MAX_LIMIT);
        $entries = $this->cronLogRepository->findRecent($limit);

        return new JsonResponse([
            'count' => count($entries),
            'limit' => $limit,
            'entries' => array_map(static fn(CronLog $c) => [
                'id' => $c->getId(),
                'runAt' => $c->getRunAt()->format('c'),
                'status' => $c->getStatus()->value,
                'durationMs' => $c->getDurationMs(),
                'tasks' => $c->getTasks(),
            ], $entries),
        ]);
    }

    private function resolveLimit(Request $request, int $default, int $max): int
    {
        $raw = $request->query->get('limit');
        if ($raw === null || $raw === '') {
            return $default;
        }

        if (!ctype_digit($raw)) {
            throw new BadRequestHttpException('limit must be a positive integer');
        }

        $limit = (int) $raw;
        if ($limit < 1) {
            throw new BadRequestHttpException('limit must be >= 1');
        }
        if ($limit > $max) {
            throw new BadRequestHttpException(sprintf('limit must be <= %d', $max));
        }

        return $limit;
    }

    private function formatTimestamp(?DateTimeImmutable $dt): ?string
    {
        return $dt?->format('c');
    }
}
