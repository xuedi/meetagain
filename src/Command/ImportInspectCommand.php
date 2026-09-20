<?php declare(strict_types=1);

namespace App\Command;

use App\Portability\ArchiveReader;
use Override;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import:inspect', description: 'Prints what MeetAgain archives hold')]
class ImportInspectCommand extends Command
{
    private const string FORMAT_JSON = 'json';
    private const string FORMAT_PLUGINS = 'plugins';
    private const string FORMAT_TABLE = 'table';

    public function __construct(
        private readonly ArchiveReader $archiveReader,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('archives', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Paths to archive ZIPs or to directories holding their export.json');
        $this->addOption(
            'format',
            null,
            InputOption::VALUE_REQUIRED,
            'json: one archive in full; plugins: the plugin keys of one archive; table: one row per archive',
            self::FORMAT_JSON,
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $errors = $io->getErrorStyle();
        $archives = array_map(strval(...), (array) $input->getArgument('archives'));
        $format = (string) $input->getOption('format');

        if (!in_array($format, [self::FORMAT_JSON, self::FORMAT_PLUGINS, self::FORMAT_TABLE], true)) {
            $errors->error(sprintf('Unknown format "%s".', $format));

            return Command::INVALID;
        }

        if ($format !== self::FORMAT_TABLE && count($archives) !== 1) {
            $errors->error(sprintf('The %s format takes exactly one archive.', $format));

            return Command::INVALID;
        }

        try {
            $descriptions = array_map($this->archiveReader->describe(...), $archives);
        } catch (RuntimeException $e) {
            $errors->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($format === self::FORMAT_JSON) {
            $output->writeln(json_encode($descriptions[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        if ($format === self::FORMAT_PLUGINS) {
            $output->writeln(implode(' ', $descriptions[0]['plugins']));

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($archives as $index => $archive) {
            $description = $descriptions[$index];
            $rows[] = [basename(rtrim($archive, '/')), $description['name'], implode(', ', $description['plugins']), $description['description']];
        }
        $io->createTable()->setHeaders(['Archive', 'Name', 'Plugins', 'Description'])->setRows($rows)->setColumnMaxWidth(3, 60)->render();

        return Command::SUCCESS;
    }
}
