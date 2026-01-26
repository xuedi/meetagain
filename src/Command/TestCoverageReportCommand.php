<?php declare(strict_types=1);

namespace App\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test:coverage-report',
    description: 'Parse coverage report and display statistics in AI-friendly format'
)]
class TestCoverageReportCommand extends Command
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
            ->addOption('threshold', null, InputOption::VALUE_REQUIRED, 'Show only files below N% coverage', '100')
            ->addOption('sort', null, InputOption::VALUE_REQUIRED, 'Sort by: coverage (default) or uncovered', 'coverage')
            ->addOption('detailed', null, InputOption::VALUE_NONE, 'Show detailed method-level coverage');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $threshold = (int) $input->getOption('threshold');
        $sortBy = $input->getOption('sort');
        $detailed = $input->getOption('detailed');

        $cloverFile = $this->kernelProjectDir . '/tests/reports/clover.xml';

        if (!file_exists($cloverFile)) {
            $output->writeln('');
            $output->writeln('═══════════════════════════════════════════════════════════════════');
            $output->writeln('  ⚠️  COVERAGE REPORT NOT FOUND');
            $output->writeln('═══════════════════════════════════════════════════════════════════');
            $output->writeln('');
            $output->writeln('The coverage report is missing: tests/reports/clover.xml');
            $output->writeln('');
            $output->writeln('To generate coverage and view the report:');
            $output->writeln('  just testCoverage           (runs tests + shows report)');
            $output->writeln('');
            $output->writeln('Or run tests manually first:');
            $output->writeln('  just test                   (runs all tests with coverage)');
            $output->writeln('  just testUnit               (unit tests only)');
            $output->writeln('  just app app:test:coverage-report');
            $output->writeln('');
            return Command::FAILURE;
        }

        $xml = simplexml_load_file($cloverFile);
        if (!$xml) {
            $output->writeln('<error>Failed to parse coverage XML</error>');
            return Command::FAILURE;
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
            if ($percentage > $threshold) {
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
        if ($sortBy === 'uncovered') {
            usort($files, fn ($a, $b) => $b['uncovered'] <=> $a['uncovered']);
        } else {
            usort($files, fn ($a, $b) => $a['percentage'] <=> $b['percentage']);
        }

        // Get total metrics
        $totalMetrics = $xml->xpath('//project/metrics')[0] ?? null;
        $totalElements = $totalMetrics ? (int) $totalMetrics['elements'] : 0;
        $totalCovered = $totalMetrics ? (int) $totalMetrics['coveredelements'] : 0;
        $totalPercentage = $totalElements > 0 ? (int) round(($totalCovered / $totalElements) * 100) : 0;

        // Output
        $output->writeln('');
        $output->writeln("COVERAGE: {$totalPercentage}% ({$totalCovered}/{$totalElements})");
        $output->writeln('---');

        if (empty($files)) {
            $output->writeln("All files above {$threshold}% threshold");
        } else {
            // Only show files below 80% (needs attention)
            $needsWork = array_filter($files, fn ($f) => $f['percentage'] < 80);

            if (!empty($needsWork)) {
                $output->writeln('NEEDS ATTENTION:');
                foreach ($needsWork as $file) {
                    $impact = $file['uncovered'] > 50 ? 'HIGH' : ($file['uncovered'] > 20 ? 'MED' : 'LOW');
                    $output->writeln(sprintf(
                        '  %3d%% %s (%d uncov) - %s',
                        $file['percentage'],
                        $file['name'],
                        $file['uncovered'],
                        $impact
                    ));
                }
            }

            // Show count of good files
            $goodFiles = count($files) - count($needsWork);
            if ($goodFiles > 0) {
                $output->writeln("\n{$goodFiles} files with 80%+ coverage (not shown)");
            }
        }

        return Command::SUCCESS;
    }
}
