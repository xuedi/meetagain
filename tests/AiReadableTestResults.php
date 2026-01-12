<?php declare(strict_types=1);

/**
 * AI-Readable Test Results Report.
 *
 * Parses PHPUnit's JUnit XML report and outputs test results
 * in a compact, AI-friendly format optimized for Haiku model consumption.
 *
 * Usage:
 *   php tests/AiReadableTestResults.php [options]
 *
 * Options:
 *   --suite=NAME     Filter by test suite name (unit, functional)
 *   --failures-only  Show only failed/errored tests
 *   --help           Show this help message
 *
 * Output format (designed for token efficiency):
 *   STATUS: PASSED|FAILED
 *   SUMMARY: X tests, Y assertions, Z failures, W errors
 *   ---
 *   [If failures exist:]
 *   FAILURES:
 *     1. ClassName::methodName
 *        File: path/to/file.php:123
 *        Type: AssertionFailure
 *        Message: Failed asserting...
 *        Expected: 'foo'
 *        Actual: 'bar'
 */

$options = [
    'suite' => null,
    'failures-only' => false,
];

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--suite=')) {
        $options['suite'] = substr($arg, 8);
    } elseif ($arg === '--failures-only') {
        $options['failures-only'] = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        echo file_get_contents(__FILE__);
        exit(0);
    }
}

$junitFile = __DIR__ . '/reports/junit.xml';

if (!file_exists($junitFile)) {
    echo "STATUS: ERROR\n";
    echo "---\n";
    echo "JUnit report not found: tests/reports/junit.xml\n";
    echo "Run tests first: just testUnit or just testFunctional\n";
    exit(1);
}

$xml = simplexml_load_file($junitFile);
if (!$xml) {
    echo "STATUS: ERROR\n";
    echo "---\n";
    echo "Failed to parse JUnit XML\n";
    exit(1);
}

$totalTests = 0;
$totalAssertions = 0;
$totalFailures = 0;
$totalErrors = 0;
$totalSkipped = 0;
$totalTime = 0.0;
$failures = [];

foreach ($xml->testsuite as $suite) {
    if ($options['suite'] !== null && (string) $suite['name'] !== $options['suite']) {
        continue;
    }

    $totalTests += (int) $suite['tests'];
    $totalAssertions += (int) $suite['assertions'];
    $totalFailures += (int) $suite['failures'];
    $totalErrors += (int) $suite['errors'];
    $totalSkipped += (int) ($suite['skipped'] ?? 0);
    $totalTime += (float) $suite['time'];

    foreach ($suite->testsuite as $classSuite) {
        foreach ($classSuite->testcase as $testcase) {
            $failure = $testcase->failure ?? $testcase->error ?? null;
            if ($failure !== null) {
                $message = trim((string) $failure);
                $failures[] = [
                    'test' => (string) $classSuite['name'] . '::' . (string) $testcase['name'],
                    'file' => (string) $testcase['file'],
                    'line' => (int) $testcase['line'],
                    'type' => isset($testcase->error) ? 'Error' : 'AssertionFailure',
                    'message' => extractMessage($message),
                    'diff' => extractDiff($message),
                ];
            }
        }
    }
}

$status = ($totalFailures === 0 && $totalErrors === 0) ? 'PASSED' : 'FAILED';

echo "STATUS: {$status}\n";
echo "SUMMARY: {$totalTests} tests, {$totalAssertions} assertions";
if ($totalFailures > 0) {
    echo ", {$totalFailures} failures";
}
if ($totalErrors > 0) {
    echo ", {$totalErrors} errors";
}
if ($totalSkipped > 0) {
    echo ", {$totalSkipped} skipped";
}
echo sprintf(" (%.2fs)\n", $totalTime);
echo "---\n";

if (!empty($failures)) {
    echo "FAILURES:\n";
    foreach ($failures as $i => $f) {
        $num = $i + 1;
        echo "\n{$num}. {$f['test']}\n";
        echo "   File: {$f['file']}:{$f['line']}\n";
        echo "   Type: {$f['type']}\n";
        if (!empty($f['message'])) {
            echo "   Message: {$f['message']}\n";
        }
        if (!empty($f['diff'])) {
            echo "   {$f['diff']}\n";
        }
    }
} elseif (!$options['failures-only']) {
    echo "All tests passed.\n";
}

exit($status === 'PASSED' ? 0 : 1);

function extractMessage(string $fullMessage): string
{
    $lines = explode("\n", $fullMessage);
    foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, 'Failed asserting') ||
            str_starts_with($line, 'Exception:') ||
            str_starts_with($line, 'Error:')) {
            return $line;
        }
    }

    return $lines[0] ?? '';
}

function extractDiff(string $fullMessage): string
{
    if (!str_contains($fullMessage, '--- Expected')) {
        return '';
    }

    $lines = explode("\n", $fullMessage);
    $inDiff = false;
    $expected = '';
    $actual = '';

    foreach ($lines as $line) {
        if (str_starts_with($line, '--- Expected')) {
            $inDiff = true;
            continue;
        }
        if (str_starts_with($line, '+++ Actual')) {
            continue;
        }
        if (str_starts_with($line, '@@ @@')) {
            continue;
        }
        if ($inDiff) {
            if (str_starts_with($line, '-')) {
                $expected = trim(substr($line, 1));
            } elseif (str_starts_with($line, '+')) {
                $actual = trim(substr($line, 1));
            }
        }
    }

    if ($expected !== '' || $actual !== '') {
        return "Expected: {$expected} | Actual: {$actual}";
    }

    return '';
}
