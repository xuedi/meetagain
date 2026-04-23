<?php declare(strict_types=1);

namespace App\Command;

use Override;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'app:translation:rename',
    description: 'Rename / move translation keys across messages.<locale>.yaml files using a YAML mapping file',
)]
class TranslationRenameCommand extends Command
{
    private const string ARG_MAPPING = 'mapping';

    private const string OPT_LOCALES = 'locales';

    private const string OPT_DRY_RUN = 'dry-run';

    private const string OPT_STRICT = 'strict';

    #[Override]
    protected function configure(): void
    {
        $this->addArgument(
            self::ARG_MAPPING,
            InputArgument::REQUIRED,
            'Path to a YAML mapping file with shape "mappings: { old_key: namespace:new_key, ... }"',
        );
        $this->addOption(
            self::OPT_LOCALES,
            'l',
            InputOption::VALUE_REQUIRED,
            'Comma-separated locales to migrate',
            'en,de,zh',
        );
        $this->addOption(
            self::OPT_DRY_RUN,
            null,
            InputOption::VALUE_NONE,
            'Print actions without writing files',
        );
        $this->addOption(
            self::OPT_STRICT,
            null,
            InputOption::VALUE_NONE,
            'Fail if any mapping key is missing from a locale (default: skip with warning)',
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $mappingPath = (string) $input->getArgument(self::ARG_MAPPING);
        $locales = array_filter(array_map('trim', explode(',', (string) $input->getOption(self::OPT_LOCALES))));
        $dryRun = (bool) $input->getOption(self::OPT_DRY_RUN);
        $strict = (bool) $input->getOption(self::OPT_STRICT);

        if (!is_file($mappingPath)) {
            $io->error('Mapping file not found: ' . $mappingPath);

            return Command::FAILURE;
        }

        $mappingData = Yaml::parseFile($mappingPath);
        if (!is_array($mappingData) || !isset($mappingData['mappings']) || !is_array($mappingData['mappings'])) {
            $io->error('Mapping file must contain a top-level "mappings:" array of "old_key: namespace:new_key" pairs.');

            return Command::FAILURE;
        }
        /** @var array<string, string> $mapping */
        $mapping = $mappingData['mappings'];

        $translationsDir = dirname(__DIR__, 2) . '/translations';
        $exitCode = Command::SUCCESS;

        foreach ($locales as $locale) {
            $file = $translationsDir . '/messages.' . $locale . '.yaml';
            if (!is_file($file)) {
                $io->warning('File not found, skipping: ' . $file);

                continue;
            }

            $parsed = Yaml::parseFile($file);
            $data = is_array($parsed) ? $parsed : [];

            $renamed = 0;
            $unknown = [];
            foreach ($mapping as $oldKey => $newKey) {
                if ($this->hasKey($data, $oldKey)) {
                    $value = $this->extractKey($data, $oldKey);
                    $this->setKey($data, $newKey, $value);
                    ++$renamed;

                    continue;
                }

                if ($this->hasKey($data, $newKey)) {
                    // Idempotent: already migrated in a previous run.
                    continue;
                }

                $unknown[] = $oldKey;
            }

            if ($unknown !== []) {
                $message = sprintf(
                    'Unknown keys in %s (not present as old or new): %s',
                    $locale,
                    implode(', ', $unknown),
                );
                if ($strict) {
                    $io->error($message);
                    $exitCode = Command::FAILURE;

                    continue;
                }
                $io->warning($message . ' - skipping these (likely missing translations, will fall back to canonical locale)');
            }

            $yaml = $this->dump($data);

            if ($dryRun) {
                $io->writeln(sprintf('[dry-run] %s: %d keys renamed', $locale, $renamed));

                continue;
            }

            file_put_contents($file, $yaml);
            $io->writeln(sprintf('%s: %d keys renamed', $locale, $renamed));
        }

        return $exitCode;
    }

    /**
     * @param array<mixed> $data
     */
    private function hasKey(array $data, string $key): bool
    {
        if (str_contains($key, ':')) {
            [$namespace, $rest] = explode(':', $key, 2);
            if (!isset($data[$namespace]) || !is_array($data[$namespace])) {
                return false;
            }

            return array_key_exists($rest, $data[$namespace]);
        }

        return array_key_exists($key, $data);
    }

    /**
     * @param array<mixed> $data
     */
    private function extractKey(array &$data, string $key): mixed
    {
        if (str_contains($key, ':')) {
            [$namespace, $rest] = explode(':', $key, 2);
            if (!isset($data[$namespace]) || !is_array($data[$namespace])) {
                throw new RuntimeException('extractKey called on missing key: ' . $key);
            }
            $value = $data[$namespace][$rest];
            unset($data[$namespace][$rest]);

            return $value;
        }

        $value = $data[$key];
        unset($data[$key]);

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    private function setKey(array &$data, string $key, mixed $value): void
    {
        if (str_contains($key, ':')) {
            [$namespace, $rest] = explode(':', $key, 2);
            if (!isset($data[$namespace]) || !is_array($data[$namespace])) {
                $data[$namespace] = [];
            }
            $data[$namespace][$rest] = $value;

            return;
        }

        $data[$key] = $value;
    }

    /**
     * @param array<mixed> $data
     */
    private function dump(array $data): string
    {
        $lines = [];
        foreach ($data as $key => $value) {
            $renderedKey = $this->formatKey((string) $key);
            if (is_array($value)) {
                if ($value === []) {
                    $lines[] = $renderedKey . ': {}';

                    continue;
                }
                $lines[] = $renderedKey . ':';
                foreach ($value as $childKey => $childValue) {
                    $lines[] = '    ' . $this->formatKey((string) $childKey) . ': ' . $this->formatValue($childValue);
                }

                continue;
            }
            $lines[] = $renderedKey . ': ' . $this->formatValue($value);
        }

        return implode("\n", $lines) . "\n";
    }

    private function formatKey(string $key): string
    {
        $reservedLiterals = [
            'true', 'false', 'null', '~',
            'yes', 'no', 'on', 'off',
            'y', 'n',
        ];
        if (in_array(strtolower($key), $reservedLiterals, true)) {
            return "'" . $key . "'";
        }
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key) === 1) {
            return $key;
        }

        return "'" . str_replace("'", "''", $key) . "'";
    }

    private function formatValue(mixed $value): string
    {
        if (is_string($value)) {
            return "'" . str_replace("'", "''", $value) . "'";
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if ($value === null) {
            return '~';
        }
        if (is_array($value)) {
            // Inline arrays as a fallback - should not occur for translation values.
            return Yaml::dump($value);
        }

        throw new RuntimeException('Unsupported value type for YAML dump');
    }
}
