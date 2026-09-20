<?php declare(strict_types=1);

namespace App\Command;

use App\ExtendedFilesystem;
use App\Portability\Exporter;
use App\Portability\InstanceScopeBuilder;
use DateTimeImmutable;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:export', description: 'Exports the whole instance into a MeetAgain archive')]
class ExportCommand extends Command
{
    public function __construct(
        private readonly Exporter $exporter,
        private readonly InstanceScopeBuilder $scopeBuilder,
        private readonly ExtendedFilesystem $fs,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('archive', InputArgument::REQUIRED, 'Path of the archive ZIP to write');
        $this->addOption(
            'anchor',
            null,
            InputOption::VALUE_REQUIRED,
            'Pin exported_at to the Monday of this date (YYYY-MM-DD) and move every date by the same whole weeks',
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $anchor = null;
        $anchorOption = $input->getOption('anchor');
        if (is_string($anchorOption)) {
            $anchor = DateTimeImmutable::createFromFormat('!Y-m-d', $anchorOption);
            if ($anchor === false) {
                $io->error('The anchor must be a date in the form YYYY-MM-DD.');

                return Command::INVALID;
            }
        }

        $target = (string) $input->getArgument('archive');
        $zipPath = $this->exporter->export($this->scopeBuilder->build(), $anchor);
        $this->fs->putFileContents($target, (string) $this->fs->getFileContents($zipPath));
        $this->fs->deleteFile($zipPath);

        $io->success(sprintf('Exported to %s.', $target));

        return Command::SUCCESS;
    }
}
