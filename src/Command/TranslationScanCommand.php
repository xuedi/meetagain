<?php declare(strict_types=1);

namespace App\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'app:translation:scan',
    description: 'Scan templates and PHP for usages of one or more translation keys',
)]
class TranslationScanCommand extends Command
{
    private const string ARG_KEYS = 'keys';

    private const string OPT_PATHS = 'paths';

    /**
     * @var list<string>
     */
    private const array DEFAULT_PATHS = ['templates', 'src'];

    #[Override]
    protected function configure(): void
    {
        $this->addArgument(
            self::ARG_KEYS,
            InputArgument::REQUIRED,
            'Comma-separated translation keys to find',
        );
        $this->addOption(
            self::OPT_PATHS,
            'p',
            InputOption::VALUE_REQUIRED,
            'Comma-separated paths to scan (relative to project root)',
            implode(',', self::DEFAULT_PATHS),
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectRoot = dirname(__DIR__, 2);

        $keys = array_filter(array_map('trim', explode(',', (string) $input->getArgument(self::ARG_KEYS))));
        $paths = array_filter(array_map('trim', explode(',', (string) $input->getOption(self::OPT_PATHS))));

        $absolutePaths = [];
        foreach ($paths as $path) {
            $abs = $projectRoot . '/' . ltrim($path, '/');
            if (!is_dir($abs)) {
                $io->warning('Path not found, skipping: ' . $path);

                continue;
            }
            $absolutePaths[] = $abs;
        }

        if ($absolutePaths === []) {
            $io->error('No valid paths to scan.');

            return Command::FAILURE;
        }

        $finder = new Finder();
        $finder->files()->in($absolutePaths)->name(['*.twig', '*.php']);

        $totalHits = 0;
        $hitsByKey = array_fill_keys($keys, 0);

        foreach ($finder as $file) {
            $contents = $file->getContents();
            $realPath = $file->getRealPath();
            $relPath = $realPath === false ? $file->getPathname() : str_replace($projectRoot . '/', '', $realPath);
            $lines = explode("\n", $contents);

            foreach ($lines as $i => $line) {
                foreach ($keys as $key) {
                    if (!$this->lineMentionsKey($line, $key)) {
                        continue;
                    }
                    $output->writeln(sprintf(
                        '%s:%d  [%s]  %s',
                        $relPath,
                        $i + 1,
                        $key,
                        trim($line),
                    ));
                    ++$totalHits;
                    ++$hitsByKey[$key];
                }
            }
        }

        $output->writeln('');
        $io->writeln(sprintf('Total hits: %d', $totalHits));
        foreach ($hitsByKey as $key => $count) {
            $io->writeln(sprintf('  %s: %d', $key, $count));
        }

        return Command::SUCCESS;
    }

    private function lineMentionsKey(string $line, string $key): bool
    {
        // Only count lines that look like translation usage to reduce false positives.
        if (!str_contains($line, $key)) {
            return false;
        }

        $patterns = [
            "'" . $key . "'",
            '"' . $key . '"',
        ];
        foreach ($patterns as $needle) {
            if (str_contains($line, $needle)) {
                return true;
            }
        }

        return false;
    }
}
