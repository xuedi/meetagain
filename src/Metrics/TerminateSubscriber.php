<?php declare(strict_types=1);

namespace App\Metrics;

use App\Metrics\Dbal\Stats;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class TerminateSubscriber implements EventSubscriberInterface
{
    private const int RUNTIME_SAMPLE_ONE_IN = 100;
    private const int BYTES_PER_MB = 1_048_576;

    public function __construct(
        private Recorder $recorder,
        private Stats $queryStats,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => ['onKernelTerminate', 1024],
            ConsoleEvents::TERMINATE => ['onConsoleTerminate', -1024],
        ];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (!$this->recorder->isEnabled()) {
            return;
        }

        $request = $event->getRequest();
        $startedAt = (float) $request->server->get('REQUEST_TIME_FLOAT', microtime(true));
        $durationMs = round((microtime(true) - $startedAt) * 1000, 1);
        $this->releaseClient();
        $route = $request->attributes->get('_route');

        $this->recorder->add(
            new Point(
                'http_request',
                [
                    'duration_ms' => $durationMs,
                    'memory_peak_mb' => round(memory_get_peak_usage(true) / self::BYTES_PER_MB, 1),
                    'db_queries' => $this->queryStats->getQueries(),
                    'db_ms' => $this->queryStats->getMilliseconds(),
                ],
                [
                    'route' => is_string($route) ? $route : '_none',
                    'method' => $request->getMethod(),
                    'status' => intdiv($event->getResponse()->getStatusCode(), 100) . 'xx',
                    'host' => $request->getHost(),
                ],
            ),
        );

        if (random_int(1, self::RUNTIME_SAMPLE_ONE_IN) === 1) {
            $this->addRuntimeSample();
        }

        $this->recorder->flush();
        memory_reset_peak_usage();
    }

    public function onConsoleTerminate(): void
    {
        $this->recorder->flush();
    }

    private function releaseClient(): void
    {
        if (function_exists('frankenphp_finish_request')) {
            frankenphp_finish_request();
        } elseif (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    private function addRuntimeSample(): void
    {
        $fields = [];

        $opcache = function_exists('opcache_get_status') ? opcache_get_status(false) : false;
        if (is_array($opcache)) {
            $memory = $opcache['memory_usage'];
            $statistics = $opcache['opcache_statistics'];
            $fields['opcache_used_mb'] = round($memory['used_memory'] / self::BYTES_PER_MB, 1);
            $fields['opcache_free_mb'] = round($memory['free_memory'] / self::BYTES_PER_MB, 1);
            $fields['opcache_wasted_mb'] = round($memory['wasted_memory'] / self::BYTES_PER_MB, 1);
            $fields['opcache_hit_rate'] = round((float) $statistics['opcache_hit_rate'], 2);
            $fields['opcache_cached_scripts'] = (int) $statistics['num_cached_scripts'];
            $fields['opcache_restarts'] = (int) ($statistics['oom_restarts'] + $statistics['hash_restarts'] + $statistics['manual_restarts']);
        }

        $apcu = function_exists('apcu_enabled') && apcu_enabled() ? apcu_sma_info(true) : false;
        if (is_array($apcu)) {
            $size = $apcu['num_seg'] * $apcu['seg_size'];
            $fields['apcu_used_mb'] = round(($size - $apcu['avail_mem']) / self::BYTES_PER_MB, 1);
        }

        if ($fields !== []) {
            $this->recorder->add(new Point('php_runtime', $fields));
        }
    }
}
