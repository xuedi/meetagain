<?php declare(strict_types=1);

namespace App\Command;

use App\Circulation\LedgerReplay;
use App\Repository\CirculationCopyRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:circulation:rebuild', description: 'Replay the circulation ledger and recompute each copy holder, or report drift')]
final class CirculationRebuildCommand extends Command
{
    public function __construct(
        private readonly LedgerReplay $replay,
        private readonly CirculationCopyRepository $copies,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('context', null, InputOption::VALUE_REQUIRED, 'Limit to one context string')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report drift without writing');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $contextOption = $input->getOption('context');
        $context = is_string($contextOption) ? $contextOption : null;
        $dryRun = (bool) $input->getOption('dry-run');

        $states = $this->replay->rebuild($context);
        $copies = $this->copies->findAllOrdered($context);
        if ($copies === []) {
            $io->success('No copies in circulation.');

            return Command::SUCCESS;
        }

        $rows = [];
        $drift = 0;
        foreach ($copies as $copy) {
            $copyId = (int) $copy->getId();
            $state = $states[$copyId] ?? null;
            if ($state === null) {
                $rows[] = [$copyId, $copy->getItemType() . '#' . $copy->getItemId(), 'no ledger entries', 'MISSING'];
                $drift++;

                continue;
            }

            $matches = $state->equals($copy->getHolder()?->getId(), $copy->getHeldSince(), $copy->getStatus());
            if ($matches) {
                continue;
            }

            $drift++;
            $rows[] = [
                $copyId,
                $copy->getItemType() . '#' . $copy->getItemId(),
                sprintf('holder %s / %s', $copy->getHolder()?->getId() ?? '-', $copy->getStatus()->value),
                sprintf('holder %s / %s', $state->holderId ?? '-', $state->status->value),
            ];

            if (!$dryRun) {
                $copy->setHolder($state->holderId === null ? null : $this->users->find($state->holderId));
                $copy->setHeldSince($state->heldSince);
                $copy->setStatus($state->status);
            }
        }

        if ($rows !== []) {
            $io->table(['copy', 'item', 'stored', 'from ledger'], $rows);
        }

        if (!$dryRun && $drift > 0) {
            $this->em->flush();
            $io->success(sprintf('Rebuilt %d copies from the ledger.', $drift));

            return Command::SUCCESS;
        }

        if ($drift > 0) {
            $io->error(sprintf('%d of %d copies drifted from the ledger.', $drift, count($copies)));

            return Command::FAILURE;
        }

        $io->success(sprintf('No drift across %d copies.', count($copies)));

        return Command::SUCCESS;
    }
}
