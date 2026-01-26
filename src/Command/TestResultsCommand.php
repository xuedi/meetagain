<?php declare(strict_types=1);

namespace App\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test:results',
    description: 'Parse JUnit XML and display test results in AI-friendly format'
)]
class TestResultsCommand extends Command
{
    public function __construct(
        private readonly string $kernelProjectDir,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('suite', null, InputOption::VALUE_REQUIRED, 'Filter by test suite name (unit, functional)')
            ->addOption('failures-only', null, InputOption::VALUE_NONE, 'Show only failed/errored tests');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $suite = $input->getOption('suite');
        $failuresOnly = $input->getOption('failures-only');

        $junitFile = $this->kernelProjectDir . '/tests/reports/junit.xml';

        if (!file_exists($junitFile)) {
            $output->writeln('STATUS: ERROR');
            $output->writeln('---');
            $output->writeln('JUnit report not found: tests/reports/junit.xml');
            $output->writeln('Run tests first: just testUnit or just testFunctional');
            return Command::FAILURE;
        }

        $xml = simplexml_load_file($junitFile);
        if (!$xml) {
            $output->writeln('STATUS: ERROR');
            $output->writeln('---');
            $output->writeln('Failed to parse JUnit XML');
            return Command::FAILURE;
        }

        $totalTests = 0;
        $totalAssertions = 0;
        $totalFailures = 0;
        $totalErrors = 0;
        $totalSkipped = 0;
        $totalTime = 0.0;
        $failures = [];

        foreach ($xml->testsuite as $suiteNode) {
            if ($suite !== null && (string) $suiteNode['name'] !== $suite) {
                continue;
            }

            $totalTests += (int) $suiteNode['tests'];
            $totalAssertions += (int) $suiteNode['assertions'];
            $totalFailures += (int) $suiteNode['failures'];
            $totalErrors += (int) $suiteNode['errors'];
            $totalSkipped += (int) ($suiteNode['skipped'] ?? 0);
            $totalTime += (float) $suiteNode['time'];

            foreach ($suiteNode->testsuite as $classSuite) {
                foreach ($classSuite->testcase as $testcase) {
                    $failure = $testcase->failure ?? $testcase->error ?? null;
                    if ($failure !== null) {
                        $message = trim((string) $failure);
                        $failures[] = [
                            'test' => (string) $classSuite['name'] . '::' . (string) $testcase['name'],
                            'file' => (string) $testcase['file'],
                            'line' => (int) $testcase['line'],
                            'type' => isset($testcase->error) ? 'Error' : 'AssertionFailure',
                            'message' => $this->extractMessage($message),
                            'diff' => $this->extractDiff($message),
                        ];
                    }
                }
            }
        }

        $status = ($totalFailures === 0 && $totalErrors === 0) ? 'PASSED' : 'FAILED';

        $output->writeln("STATUS: {$status}");
        $summary = "{$totalTests} tests, {$totalAssertions} assertions";
        if ($totalFailures > 0) {
            $summary .= ", {$totalFailures} failures";
        }
        if ($totalErrors > 0) {
            $summary .= ", {$totalErrors} errors";
        }
        if ($totalSkipped > 0) {
            $summary .= ", {$totalSkipped} skipped";
        }
        $output->writeln(sprintf('SUMMARY: %s (%.2fs)', $summary, $totalTime));
        $output->writeln('---');

        if (!empty($failures)) {
            $output->writeln('FAILURES:');
            foreach ($failures as $i => $f) {
                $num = $i + 1;
                $output->writeln("\n{$num}. {$f['test']}");
                $output->writeln("   File: {$f['file']}:{$f['line']}");
                $output->writeln("   Type: {$f['type']}");
                if (!empty($f['message'])) {
                    $output->writeln("   Message: {$f['message']}");
                }
                if (!empty($f['diff'])) {
                    $output->writeln("   {$f['diff']}");
                }
            }
        } elseif (!$failuresOnly) {
            $output->writeln('All tests passed.');
        }

        return $status === 'PASSED' ? Command::SUCCESS : Command::FAILURE;
    }

    private function extractMessage(string $fullMessage): string
    {
        $lines = explode("\n", $fullMessage);
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'Failed asserting') || str_starts_with($line, 'Exception:') || str_starts_with($line, 'Error:')) {
                return $line;
            }
        }

        return $lines[0];
    }

    private function extractDiff(string $fullMessage): string
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
}
