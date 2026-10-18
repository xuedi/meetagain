<?php declare(strict_types=1);

namespace App\Command;

use App\Storage\AdapterFactory;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToProvideChecksum;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:storage:copy',
    description: 'Copy images, thumbnails and invoices from the local disk into the configured storage, or back with --reverse',
)]
final class StorageCopyCommand extends Command
{
    /** @var array<string, string> the part of each storage that is persistent data; pending imports are left behind */
    private const array DIRECTORIES = [
        AdapterFactory::IMAGES => '',
        AdapterFactory::THUMBNAILS => '',
        AdapterFactory::PRIVATE => 'invoices',
    ];

    public function __construct(
        private readonly AdapterFactory $storages,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('reverse', null, InputOption::VALUE_NONE, 'Copy from the configured storage back to the local disk');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->storages->isRemote()) {
            $io->error('STORAGE_DSN is not set, so the configured storage is the local disk itself. There is nothing to copy.');

            return Command::FAILURE;
        }

        $reverse = (bool) $input->getOption('reverse');
        $rows = [];
        $failures = 0;
        foreach (self::DIRECTORIES as $name => $directory) {
            $local = $this->storages->createLocal($name);
            $remote = $this->storages->create($name);
            [$source, $target] = $reverse ? [$remote, $local] : [$local, $remote];

            [$copied, $skipped, $failed] = $this->copy($source, $target, $directory, $io);
            $failures += count($failed);
            $rows[] = [$name, $copied, $skipped, count($failed)];
        }

        $io->table(['Storage', 'Copied', 'Already there', 'Failed'], $rows);
        if ($failures > 0) {
            $io->error(sprintf('%d file(s) did not arrive intact. Run the command again; files already copied are skipped.', $failures));

            return Command::FAILURE;
        }

        $io->success($reverse ? 'The configured storage is copied back to the local disk.' : 'The local disk is copied into the configured storage.');

        return Command::SUCCESS;
    }

    /**
     * @return array{int, int, list<string>}
     */
    private function copy(FilesystemOperator $source, FilesystemOperator $target, string $directory, SymfonyStyle $io): array
    {
        $copied = 0;
        $skipped = 0;
        $failed = [];
        foreach ($source->listContents($directory, true) as $item) {
            if (!$item instanceof FileAttributes || str_starts_with(basename($item->path()), '.')) {
                continue;
            }

            $key = $item->path();
            try {
                if ($target->fileExists($key) && $this->matches($source, $target, $key)) {
                    $skipped++;
                    continue;
                }

                $stream = $source->readStream($key);
                $target->writeStream($key, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                if (!$this->matches($source, $target, $key)) {
                    $failed[] = $key;
                    $io->warning(sprintf('%s differs after the copy.', $key));
                    continue;
                }
                $copied++;
            } catch (FilesystemException $e) {
                $failed[] = $key;
                $io->warning(sprintf('%s: %s', $key, $e->getMessage()));
            }
        }

        return [$copied, $skipped, $failed];
    }

    private function matches(FilesystemOperator $source, FilesystemOperator $target, string $key): bool
    {
        if ($source->fileSize($key) !== $target->fileSize($key)) {
            return false;
        }

        try {
            return $source->checksum($key, ['checksum_algo' => 'md5']) === $target->checksum($key, ['checksum_algo' => 'md5']);
        } catch (UnableToProvideChecksum) {
            return true;
        }
    }
}
