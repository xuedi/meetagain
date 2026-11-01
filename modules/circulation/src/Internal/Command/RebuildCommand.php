<?php declare(strict_types=1);

namespace Module\Circulation\Internal\Command;

use Module\Circulation\Internal\RebuildAction;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:circulation:rebuild', description: 'Replay the circulation ledger and recompute each copy holder, or report drift')]
final class RebuildCommand extends Command
{
    public function __construct(
        private readonly RebuildAction $action,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('context', null, InputOption::VALUE_REQUIRED, 'Limit to one context string')->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Report drift without writing',
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $contextOption = $input->getOption('context');
        $dryRun = (bool) $input->getOption('dry-run');

        $rebuilt = $this->action->rebuild(is_string($contextOption) ? $contextOption : null, $dryRun);
        if ($rebuilt['copies'] === 0) {
            $io->success('No copies in circulation.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($rebuilt['drifted'] as ['copy' => $copy, 'state' => $state, 'storedHolderId' => $holderId, 'storedStatus' => $status]) {
            $rows[] = [
                (int) $copy->getId(),
                $copy->getItemType() . '#' . $copy->getItemId(),
                $state === null ? 'no ledger entries' : sprintf('holder %s / %s', $holderId ?? '-', $status->value),
                $state === null ? 'MISSING' : sprintf('holder %s / %s', $state->holderId ?? '-', $state->status->value),
            ];
        }

        if ($rows !== []) {
            $io->table(['copy', 'item', 'stored', 'from ledger'], $rows);
        }

        $drift = count($rebuilt['drifted']);
        if (!$dryRun && $drift > 0) {
            $io->success(sprintf('Rebuilt %d copies from the ledger.', $drift));

            return Command::SUCCESS;
        }

        if ($drift > 0) {
            $io->error(sprintf('%d of %d copies drifted from the ledger.', $drift, $rebuilt['copies']));

            return Command::FAILURE;
        }

        $io->success(sprintf('No drift across %d copies.', $rebuilt['copies']));

        return Command::SUCCESS;
    }
}
