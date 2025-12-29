<?php declare(strict_types=1);

/**
 * AI-Readable Route Metrics Report
 *
 * Analyzes Symfony routes and outputs performance metrics (SQL queries, timing, memory)
 * in a simple, human/AI-readable format.
 *
 * Usage:
 *   php tests/AiReadableRouteMetrics.php [options]
 *
 * Options:
 *   --threshold=N    Show only routes with >N queries (default: 20)
 *   --sort=queries   Sort by query count descending (default)
 *   --sort=time      Sort by execution time descending
 *   --sort=memory    Sort by memory usage descending
 *   --filter=PATTERN Filter routes by name pattern
 *   --json           Output as JSON
 *   --help           Show this help message
 */

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

// Set environment variables before loading anything
$_SERVER['APP_ENV'] = 'test';
$_SERVER['KERNEL_CLASS'] = 'App\Kernel';

require_once __DIR__ . '/bootstrap.php';

// Parse command line arguments
$options = [
    'threshold' => 20,
    'sort' => 'queries',
    'filter' => null,
    'json' => false,
    'verbose' => false,
];

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--threshold=')) {
        $options['threshold'] = (int) substr($arg, 12);
    } elseif (str_starts_with($arg, '--sort=')) {
        $options['sort'] = substr($arg, 7);
    } elseif (str_starts_with($arg, '--filter=')) {
        $options['filter'] = substr($arg, 9);
    } elseif ($arg === '--json') {
        $options['json'] = true;
    } elseif ($arg === '--verbose' || $arg === '-v') {
        $options['verbose'] = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        echo file_get_contents(__FILE__);
        exit(0);
    }
}

/**
 * Route Metrics Analyzer using Symfony's WebTestCase
 */
class RouteMetricsAnalyzer extends WebTestCase
{
    public function runAnalysis(array $options): array
    {
        $client = static::createClient();
        $container = static::getContainer();
        $router = $container->get('router');
        $profiler = $container->get('profiler');

        $results = [];
        $skipped = [];

        foreach ($router->getRouteCollection() as $name => $route) {
            // Skip internal routes
            if (str_starts_with($name, '_')) {
                continue;
            }

            // Skip non-GET routes
            $methods = $route->getMethods();
            if (!empty($methods) && !in_array('GET', $methods, true)) {
                continue;
            }

            $path = $route->getPath();

            // Skip admin routes
            if (str_contains($path, '/admin')) {
                $skipped[] = ['name' => $name, 'reason' => 'admin', 'path' => $path];
                continue;
            }

            // Skip parametrized routes (but allow {_locale} which we handle)
            $pathWithoutLocale = str_replace('/{_locale}', '', $path);
            if (str_contains($pathWithoutLocale, '{')) {
                $skipped[] = ['name' => $name, 'reason' => 'params', 'path' => $path];
                continue;
            }

            // Debug: show routes we're attempting to analyze
            if ($options['verbose']) {
                fwrite(STDERR, "Analyzing: {$name} -> {$path}\n");
            }

            // Apply name filter
            if ($options['filter'] !== null && !str_contains($name, $options['filter'])) {
                continue;
            }

            // Make request and collect metrics
            $testPath = $path;
            // Replace {_locale} with 'en' for testing
            if (str_contains($testPath, '{_locale}')) {
                $testPath = str_replace('{_locale}', 'en', $testPath);
            } elseif (!str_starts_with($testPath, '/') || $testPath === '/') {
                // Handle root path or add locale
                if ($testPath === '/') {
                    $testPath = '/en/';
                } elseif (!str_starts_with($testPath, '/api') && !str_starts_with($testPath, '/ajax')) {
                    $testPath = '/en' . $testPath;
                }
            }

            $metrics = $this->collectMetrics($client, $profiler, $testPath, $name, $options['verbose']);
            if ($metrics !== null) {
                $results[] = $metrics;
            }
        }

        return ['results' => $results, 'skipped' => $skipped];
    }

    private function collectMetrics($client, $profiler, string $path, string $routeName, bool $verbose = false): ?array
    {
        try {
            $client->enableProfiler();
            $client->request('GET', $path);
            $response = $client->getResponse();
            $profile = $profiler->loadProfileFromResponse($response);

            if ($profile === null) {
                if ($verbose) {
                    fwrite(STDERR, "  -> No profile for {$path} (status: {$response->getStatusCode()})\n");
                }
                return null;
            }

            $dbCollector = $profile->getCollector('db');
            $timeCollector = $profile->getCollector('time');
            $memoryCollector = $profile->getCollector('memory');

            return [
                'route' => $routeName,
                'path' => $path,
                'status' => $response->getStatusCode(),
                'queries' => $dbCollector->getQueryCount(),
                'sql_time' => round($dbCollector->getTime(), 2),
                'total_time' => round($timeCollector->getDuration(), 2),
                'memory' => round($memoryCollector->getMemory() / 1024 / 1024, 2),
            ];
        } catch (\Throwable $e) {
            return [
                'route' => $routeName,
                'path' => $path,
                'status' => 'ERROR',
                'queries' => 0,
                'sql_time' => 0,
                'total_time' => 0,
                'memory' => 0,
                'error' => substr($e->getMessage(), 0, 100),
            ];
        }
    }
}

// Run analysis
$analyzer = new RouteMetricsAnalyzer('runAnalysis');
$data = $analyzer->runAnalysis($options);
$results = $data['results'];
$skipped = $data['skipped'];

// Sort results
usort($results, function ($a, $b) use ($options) {
    return match ($options['sort']) {
        'time' => $b['total_time'] <=> $a['total_time'],
        'memory' => $b['memory'] <=> $a['memory'],
        default => $b['queries'] <=> $a['queries'],
    };
});

// JSON output
if ($options['json']) {
    echo json_encode($results, JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

// Text output
$totalRoutes = count($results) + count($skipped);
$analyzedCount = count($results);
$skippedCount = count($skipped);

echo "\n";
echo "ROUTE METRICS ANALYSIS\n";
echo "===\n";
echo "Total: {$totalRoutes} | Analyzed: {$analyzedCount} | Skipped: {$skippedCount} (admin/params)\n";
echo "---\n";

// High query count section
$highQueries = array_filter($results, fn($r) => $r['queries'] > $options['threshold']);
if (!empty($highQueries)) {
    echo "\nHIGH QUERY COUNT (>{$options['threshold']}):\n";
    foreach ($highQueries as $r) {
        printf(
            "  %3d queries | %6.0fms | %-30s | %s\n",
            $r['queries'],
            $r['total_time'],
            $r['route'],
            $r['path']
        );
    }
}

// Errors section
$errors = array_filter($results, fn($r) => $r['status'] === 'ERROR' || $r['status'] >= 400);
if (!empty($errors)) {
    echo "\nERRORS:\n";
    foreach ($errors as $r) {
        printf(
            "  %s | %-30s | %s\n",
            $r['status'],
            $r['route'],
            $r['error'] ?? $r['path']
        );
    }
}

// All routes summary
echo "\nALL ROUTES (by {$options['sort']}):\n";
printf("  %-5s | %-30s | %-35s | %s | %s\n", "Qry", "Route", "Path", "Time", "Mem");
echo "  " . str_repeat("-", 100) . "\n";

foreach ($results as $r) {
    if ($r['status'] !== 'ERROR') {
        printf(
            "  %5d | %-30s | %-35s | %6.0fms | %.1fMB\n",
            $r['queries'],
            substr($r['route'], 0, 30),
            substr($r['path'], 0, 35),
            $r['total_time'],
            $r['memory']
        );
    }
}

echo "\n";
exit(0);
