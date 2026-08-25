<?php declare(strict_types=1);

namespace Module\Trust\Internal\Command;

use Module\Trust\Internal\ContextRegistry;
use Module\Trust\Internal\ScoreProvider;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:trust:rebuild', description: 'Recompute trust scores from their sources, or report drift against the cache')]
final class RebuildCommand extends Command
{
    public function __construct(
        private readonly ContextRegistry $registry,
        private readonly ScoreProvider $scoreProvider,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('context', null, InputOption::VALUE_REQUIRED, 'Limit to one context string')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Compare against the cache and report drift without writing');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $only = $input->getOption('context');
        $dryRun = (bool) $input->getOption('dry-run');

        $descriptors = $this->registry->describeAll();
        if (is_string($only)) {
            $descriptors = array_values(array_filter($descriptors, static fn($descriptor): bool => $descriptor->context === $only));
            if ($descriptors === []) {
                $io->error('No describer claims that context.');

                return Command::FAILURE;
            }
        }

        if ($descriptors === []) {
            $io->warning('No contexts are described. Trust is inert until a consumer registers one.');

            return Command::SUCCESS;
        }

        $drifted = 0;
        foreach ($descriptors as $descriptor) {
            $fresh = $this->scoreProvider->calculate($descriptor->context);

            if ($dryRun) {
                $cached = $this->scoreProvider->getMap($descriptor->context);
                $differs = $cached !== $fresh;
                $drifted += $differs ? 1 : 0;
                $io->section($descriptor->label);
                $io->text($differs ? 'DRIFT: cached map differs from a fresh computation' : 'no drift');
            } else {
                $this->scoreProvider->invalidate($descriptor->context);
                $io->section($descriptor->label);
            }

            $rows = [];
            foreach ($fresh as $userId => $score) {
                $rows[] = [$userId, $score];
            }
            $io->table(['user', 'score'], $rows);
        }

        if ($dryRun && $drifted > 0) {
            $io->error(sprintf('%d context(s) drifted.', $drifted));

            return Command::FAILURE;
        }

        $io->success($dryRun ? 'No drift.' : 'Rebuilt.');

        return Command::SUCCESS;
    }
}
