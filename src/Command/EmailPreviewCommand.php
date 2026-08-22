<?php declare(strict_types=1);

namespace App\Command;

use App\Service\Email\PreviewSweepResult;
use App\Service\Email\PreviewSweepService;
use Override;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:email:preview', description: 'Enqueues and sends one mail per email type and language for a visual pass')]
class EmailPreviewCommand extends Command
{
    public function __construct(
        protected readonly PreviewSweepService $sweep,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('lang', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Limit the sweep to these languages');
        $this->addOption('type', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Limit the sweep to these email type identifiers');
        $this->addOption('to', null, InputOption::VALUE_REQUIRED, 'Recipient domain', PreviewSweepService::DEFAULT_RECIPIENT_DOMAIN);
        $this->addOption('plain', null, InputOption::VALUE_NONE, 'Leave subjects untagged, the way a recipient would see them');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = $this->sweep->sweep(
                $input->getOption('type'),
                $input->getOption('lang'),
                (string) $input->getOption('to'),
                !$input->getOption('plain'),
            );
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        return $this->report($io, $result);
    }

    protected function report(SymfonyStyle $io, PreviewSweepResult $result): int
    {
        $io->success(sprintf(
            'Queued %d (%d templates x %d languages) - dispatch with: just appCron',
            $result->enqueued,
            count($result->identifiers),
            count($result->locales),
        ));
        $io->text(sprintf(
            'Newest first in the inbox once dispatched: %s, each language A-Z by identifier',
            implode(', ', $result->locales),
        ));
        $io->text($result->tagged
            ? 'Subjects tagged [identifier][resolved site name][language] - pass --plain to see them untagged'
            : 'Subjects untagged');

        if ($result->withoutType !== []) {
            $io->warning(sprintf(
                'Skipped %d template(s) with no email type behind them: %s',
                count($result->withoutType),
                implode(', ', $result->withoutType),
            ));
        }

        if ($result->errors === []) {
            return Command::SUCCESS;
        }

        foreach ($result->errors as $recipient => $reason) {
            $io->text(sprintf('<error>%s</error> %s', $recipient, $reason));
        }

        return Command::FAILURE;
    }
}
