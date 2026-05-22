<?php declare(strict_types=1);

namespace App\Command\Security;

use JsonException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;

#[AsCommand(
    name: 'app:security:report-combined',
    description: 'Combine attack-test verdict + k6 summary JSON files into a single HTML index.',
)]
final class SecurityReportCombinedCommand extends Command
{
    public function __construct(
        private readonly Environment $twig,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption(
            'input-dir',
            null,
            InputOption::VALUE_REQUIRED,
            'Directory containing *.verdict.json and *.k6-summary.json',
            'tests/reports/attack',
        )->addOption(
            'output',
            null,
            InputOption::VALUE_REQUIRED,
            'HTML output path',
            'tests/reports/attack/index.html',
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inputDir = (string) $input->getOption('input-dir');
        $outputPath = (string) $input->getOption('output');

        if (!is_dir($inputDir)) {
            $output->writeln(sprintf('<error>Input directory not found: %s</error>', $inputDir));
            return Command::FAILURE;
        }

        $verdictFiles = glob(rtrim($inputDir, '/') . '/*.verdict.json') ?: [];
        if ($verdictFiles === []) {
            $output->writeln(sprintf('<comment>No verdict files in %s; nothing to combine.</comment>', $inputDir));
            return Command::SUCCESS;
        }

        $scenarios = [];
        foreach ($verdictFiles as $verdictFile) {
            $scenarios[] = $this->loadScenario($verdictFile, $inputDir);
        }

        $allPassed = true;
        foreach ($scenarios as $scenario) {
            if ($scenario['allPassed'] !== false) {
                continue;
            }

            $allPassed = false;
            break;
        }

        $html = $this->twig->render('security/attack_report_index.html.twig', [
            'scenarios' => $scenarios,
            'allPassed' => $allPassed,
            'generatedAt' => date(DATE_ATOM),
        ]);

        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true) && !is_dir($outputDir)) {
            $output->writeln(sprintf('<error>Could not create output dir: %s</error>', $outputDir));
            return Command::FAILURE;
        }
        file_put_contents($outputPath, $html);

        $output->writeln(sprintf(
            'Combined report: %s (%d scenarios, %s)',
            $outputPath,
            count($scenarios),
            $allPassed ? 'all PASS' : 'one or more FAIL',
        ));

        return $allPassed ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return array{name: string, allPassed: bool, verdicts: list<array<string, mixed>>, k6: array<string, mixed>, dashboard: ?string}
     */
    private function loadScenario(string $verdictFile, string $inputDir): array
    {
        $name = preg_replace('/\.verdict\.json$/', '', basename($verdictFile)) ?? 'unknown';
        $verdict = $this->readJson($verdictFile);

        $k6File = rtrim($inputDir, '/') . '/' . $name . '.k6-summary.json';
        $k6 = is_readable($k6File) ? $this->readJson($k6File) : [];

        $dashboardFile = rtrim($inputDir, '/') . '/' . $name . '.html';
        $dashboard = is_readable($dashboardFile) ? basename($dashboardFile) : null;

        return [
            'name' => $name,
            'allPassed' => (bool) ($verdict['allPassed'] ?? false),
            'verdicts' => is_array($verdict['verdicts'] ?? null) ? $verdict['verdicts'] : [],
            'k6' => is_array($k6['metrics'] ?? null) ? $k6['metrics'] : [],
            'dashboard' => $dashboard,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        try {
            $raw = (string) file_get_contents($path);
            $value = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            return is_array($value) ? $value : [];
        } catch (JsonException) {
            return [];
        }
    }
}
