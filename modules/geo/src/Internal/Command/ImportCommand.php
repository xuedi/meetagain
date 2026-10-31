<?php declare(strict_types=1);

namespace Module\Geo\Internal\Command;

use Module\Geo\Internal\Import\Dataset;
use Module\Geo\Internal\Import\Loader;
use Module\Geo\Internal\Import\Options;
use Module\Geo\Internal\ImportAction;
use Module\Task\Contract\Status;
use Override;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ResetInterface;

#[AsCommand(name: 'app:geo:import', description: 'Import countries, states, cities, districts and their names from GeoNames')]
final class ImportCommand extends Command
{
    private const string ALL = 'all';

    public function __construct(
        private readonly Loader $loader,
        private readonly ImportAction $action,
        #[Autowire(service: 'services_resetter')]
        private readonly ResetInterface $resetter,
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
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'GeoNames download address', Options::DEFAULT_BASE_URL)
            ->addOption(
                'min-population',
                null,
                InputOption::VALUE_REQUIRED,
                'Smallest city to import, picks the cities dump',
                (string) Options::DEFAULT_MIN_POPULATION,
            );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string) $input->getArgument('dataset');
        $dataset = Dataset::tryFrom($name);
        if ($dataset === null && $name !== self::ALL) {
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

        if ($dataset === null) {
            return $this->walk($io, $options);
        }

        try {
            $progress = $io->createProgressBar();
            $report = $this->loader->import($dataset, $options, $progress->advance(...));
            $progress->finish();
            $io->newLine();
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->table(['Dataset', 'Created', 'Updated', 'Unchanged', 'Skipped'], [[
            $report->dataset->value,
            $report->created,
            $report->updated,
            $report->unchanged,
            $report->skipped,
        ]]);

        return Command::SUCCESS;
    }

    private function walk(SymfonyStyle $io, Options $options): int
    {
        $cursor = null;
        do {
            $progress = $io->createProgressBar();
            $result = $this->action->step($options, $cursor, $progress->advance(...));
            $progress->clear();
            $io->writeln($result->message);
            if ($result->status !== Status::ok) {
                $io->error($result->message);

                return Command::FAILURE;
            }
            $cursor = $result->continueFrom;
            $this->resetter->reset();
        } while ($cursor !== null);

        return Command::SUCCESS;
    }
}
