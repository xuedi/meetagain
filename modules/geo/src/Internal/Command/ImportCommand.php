<?php declare(strict_types=1);

namespace Module\Geo\Internal\Command;

use Module\Geo\Internal\Import\Dataset;
use Module\Geo\Internal\Import\Loader;
use Module\Geo\Internal\Import\Report;
use Module\Geo\Internal\ImportAction;
use Override;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:geo:import', description: 'Import countries, states, cities, districts and their names from GeoNames')]
final class ImportCommand extends Command
{
    private const string ALL = 'all';

    public function __construct(
        private readonly Loader $loader,
        private readonly ImportAction $action,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $datasets = implode(', ', [...array_map(static fn(Dataset $dataset): string => $dataset->value, Dataset::cases()), self::ALL]);
        $this
            ->addArgument('dataset', InputArgument::REQUIRED, 'One of: ' . $datasets)
            ->addOption('country', null, InputOption::VALUE_REQUIRED, 'Limit to one country, by ISO2 code')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Read the GeoNames files from this directory instead of downloading them')
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'GeoNames download address; defaults to the one saved on the Maintenance page')
            ->addOption(
                'min-population',
                null,
                InputOption::VALUE_REQUIRED,
                'Smallest city to import, picks the cities dump; defaults to the one saved on the Maintenance page',
            );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string) $input->getArgument('dataset');
        $datasets = $name === self::ALL ? Dataset::cases() : array_filter([Dataset::tryFrom($name)]);
        if ($datasets === []) {
            $io->error(sprintf('Unknown dataset "%s".', $name));

            return Command::INVALID;
        }

        $source = $input->getOption('source');
        $baseUrl = $input->getOption('base-url');
        $minPopulation = $input->getOption('min-population');
        $options = $this->action->options(
            country: is_string($input->getOption('country')) ? $input->getOption('country') : null,
            sourceDir: is_string($source) ? $source : null,
            baseUrl: is_string($baseUrl) ? $baseUrl : null,
            minPopulation: is_string($minPopulation) ? (int) $minPopulation : null,
        );

        $reports = [];
        try {
            foreach ($datasets as $dataset) {
                $io->section($dataset->value);
                $progress = $io->createProgressBar();
                $reports[] = $this->loader->import($dataset, $options, $progress->advance(...));
                $progress->finish();
                $io->newLine();
            }
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->table(['Dataset', 'Created', 'Updated', 'Unchanged', 'Skipped'], array_map(static fn(Report $report): array => [
            $report->dataset->value,
            $report->created,
            $report->updated,
            $report->unchanged,
            $report->skipped,
        ], $reports));

        return Command::SUCCESS;
    }
}
