<?php declare(strict_types=1);

/**
 * AI-Readable Test Coverage Report.
 *
 * Parses PHPUnit's clover.xml coverage report and outputs coverage statistics
 * in a simple, human/AI-readable format.
 *
 * Usage:
 *   php tests/AiReadableTestCoverage.php [options]
 *
 * Options:
 *   --threshold=N    Show only files below N% coverage (default: 100)
 *   --sort=coverage  Sort by coverage % ascending (default)
 *   --sort=uncovered Sort by uncovered element count descending
 *   --detailed       Show method-level coverage (not just file-level)
 *   --help           Show this help message
 */

// Parse command line arguments
$options = [
    'threshold' => 100,
    'sort' => 'coverage',
    'detailed' => false,
];

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--threshold=')) {
        $options['threshold'] = (int) substr($arg, 12);
    } elseif (str_starts_with($arg, '--sort=')) {
        $options['sort'] = substr($arg, 7);
    } elseif ($arg === '--detailed') {
        $options['detailed'] = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        echo file_get_contents(__FILE__);
        exit(0);
    }
}

$cloverFile = __DIR__ . '/reports/clover.xml';

if (!file_exists($cloverFile)) {
    echo "\n";
    echo "═══════════════════════════════════════════════════════════════════\n";
    echo "  ⚠️  COVERAGE REPORT NOT FOUND\n";
    echo "═══════════════════════════════════════════════════════════════════\n";
    echo "\n";
    echo "The coverage report is missing: tests/reports/clover.xml\n";
    echo "\n";
    echo "To generate coverage and view the report:\n";
    echo "  just showCoverage           (runs tests + shows report)\n";
    echo "\n";
    echo "Or run tests manually first:\n";
    echo "  just test                   (runs all tests with coverage)\n";
    echo "  just testUnit               (unit tests only)\n";
    echo "  php tests/AiReadableTestCoverage.php\n";
    echo "\n";
    exit(1);
}

// Parse XML
$xml = simplexml_load_file($cloverFile);
if (!$xml) {
    echo "Error: Failed to parse coverage XML\n";
    exit(1);
}

// Extract file coverage
$files = [];
foreach ($xml->xpath('//file') as $file) {
    $path = (string) $file['name'];

    // Only include files from Service and Security directories
    if (!str_contains($path, '/Service/') && !str_contains($path, '/Security/')) {
        continue;
    }

    // Get class/file metrics (not method metrics)
    $metrics = $file->xpath('.//metrics[@complexity]');
    if (empty($metrics)) {
        continue;
    }

    $metric = $metrics[0];
    $elements = (int) $metric['elements'];
    $covered = (int) $metric['coveredelements'];

    if ($elements === 0) {
        continue;
    }

    $percentage = (int) round(($covered / $elements) * 100);
    $uncovered = $elements - $covered;
    $basename = basename($path);

    // Apply threshold filter
    if ($percentage > $options['threshold']) {
        continue;
    }

    $files[] = [
        'name' => $basename,
        'path' => $path,
        'percentage' => $percentage,
        'covered' => $covered,
        'total' => $elements,
        'uncovered' => $uncovered,
    ];
}

// Sort
if ($options['sort'] === 'uncovered') {
    usort($files, fn ($a, $b) => $b['uncovered'] <=> $a['uncovered']);
} else {
    usort($files, fn ($a, $b) => $a['percentage'] <=> $b['percentage']);
}

// Get total metrics
$totalMetrics = $xml->xpath('//project/metrics')[0] ?? null;
$totalElements = $totalMetrics ? (int) $totalMetrics['elements'] : 0;
$totalCovered = $totalMetrics ? (int) $totalMetrics['coveredelements'] : 0;
$totalPercentage = $totalElements > 0 ? (int) round(($totalCovered / $totalElements) * 100) : 0;

// Output - Compact format
echo "\n";
echo "COVERAGE: {$totalPercentage}% ({$totalCovered}/{$totalElements})\n";
echo "---\n";

if (empty($files)) {
    echo "All files above {$options['threshold']}% threshold\n";
} else {
    // Only show files below 80% (needs attention)
    $needsWork = array_filter($files, fn ($f) => $f['percentage'] < 80);

    if (!empty($needsWork)) {
        echo "NEEDS ATTENTION:\n";
        foreach ($needsWork as $file) {
            $impact = $file['uncovered'] > 50 ? 'HIGH' : ($file['uncovered'] > 20 ? 'MED' : 'LOW');
            printf(
                "  %3d%% %s (%d uncov) - %s\n",
                $file['percentage'],
                $file['name'],
                $file['uncovered'],
                $impact
            );
        }
    }

    // Show count of good files
    $goodFiles = count($files) - count($needsWork);
    if ($goodFiles > 0) {
        echo "\n{$goodFiles} files with 80%+ coverage (not shown)\n";
    }
}

exit(0);
