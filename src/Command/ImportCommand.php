<?php declare(strict_types=1);

namespace App\Command;

use App\Portability\Importer;
use App\Portability\ImportSummary;
use App\Portability\Outcome;
use Override;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import', description: 'Imports a MeetAgain archive into this instance')]
class ImportCommand extends Command
{
    public function __construct(
        private readonly Importer $importer,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('archive', InputArgument::REQUIRED, 'Path to the archive ZIP or to a directory holding its export.json');
        $this->addOption(
            'shift-dates',
            null,
            InputOption::VALUE_NONE,
            'Move every date by whole weeks, from the week the archive was exported to the current week',
        );
        $this->addOption(
            'site-settings',
            null,
            InputOption::VALUE_NONE,
            'Write the archive site block (name, languages, theme, logo, feature settings) into this instance',
        );
        $this->addOption('strict', null, InputOption::VALUE_NONE, 'Exit non-zero when any row was skipped or dropped');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $summary = $this->importer->import(
                (string) $input->getArgument('archive'),
                shiftDates: (bool) $input->getOption('shift-dates'),
                applySite: (bool) $input->getOption('site-settings'),
            );
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->printSummary($io, $summary);

        $losses = $summary->getLosses();
        if ($input->getOption('strict') && $losses !== []) {
            $lost = array_map(static fn(string $kind, int $rows): string => sprintf('%s (%d)', $kind, $rows), array_keys($losses), $losses);
            $io->error('Rows were skipped or dropped: ' . implode(', ', $lost));

            return Command::FAILURE;
        }

        $io->success('Import complete.');

        return Command::SUCCESS;
    }

    private function printSummary(SymfonyStyle $io, ImportSummary $summary): void
    {
        $rows = [];
        foreach (array_keys($summary->counts) as $kind) {
            $rows[] = [$kind, ...array_map(static fn(Outcome $outcome): int => $summary->get($kind, $outcome), Outcome::cases())];
        }
        $io->table(['', ...array_map(static fn(Outcome $outcome): string => $outcome->value, Outcome::cases())], $rows);

        if ($summary->weeksShifted !== 0) {
            $io->text(sprintf('Dates moved by %d weeks.', $summary->weeksShifted));
        }

        if ($summary->siteApplied) {
            $io->text('Site settings written. Rebuild the theme for new colours to show.');
        }

        if ($summary->missingPlugins !== []) {
            $io->warning('The archive uses plugins that are not active here: ' . implode(', ', $summary->missingPlugins));
        }
    }
}
