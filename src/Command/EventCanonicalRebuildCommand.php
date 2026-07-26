<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\EventSeries;
use App\Repository\EventSeriesRepository;
use App\Service\Seo\EventCanonicalRebuildService;
use App\ValueObject\CanonicalRebuildSummary;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:event:canonical-rebuild', description: 'Recompute the canonical markers of one or all event series')]
class EventCanonicalRebuildCommand extends Command
{
    public function __construct(
        private readonly EventCanonicalRebuildService $rebuildService,
        private readonly EventSeriesRepository $seriesRepository,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('series', InputArgument::OPTIONAL, 'Rebuild only this series id');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $seriesArgument = $input->getArgument('series');

        if ($seriesArgument === null) {
            $this->renderSummaries($io, $this->rebuildService->rebuildAll());

            return Command::SUCCESS;
        }

        $series = $this->seriesRepository->find((int) $seriesArgument);
        if (!$series instanceof EventSeries) {
            $io->error(sprintf('No event series with id %s', $seriesArgument));

            return Command::FAILURE;
        }

        $this->renderSummaries($io, $this->rebuildService->rebuildSeries($series));

        return Command::SUCCESS;
    }

    /**
     * @param array<CanonicalRebuildSummary> $summaries
     */
    private function renderSummaries(SymfonyStyle $io, array $summaries): void
    {
        if ($summaries === []) {
            $io->writeln('Nothing to rebuild.');

            return;
        }

        $io->table(
            ['Locale', 'Members', 'Roots', 'Detached', 'Removed'],
            array_map(static fn(CanonicalRebuildSummary $s) => [
                $s->locale,
                $s->membersScanned,
                $s->rootsWritten,
                $s->detachedWritten,
                $s->markersRemoved,
            ], $summaries),
        );
    }
}
