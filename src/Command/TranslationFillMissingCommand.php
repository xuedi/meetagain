<?php declare(strict_types=1);

namespace App\Command;

use App\Service\CommandService;
use App\Service\LanguageService;
use App\Service\TranslationFileManager;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:translation:fill-missing',
    description: 'Generates SQL statements for missing translations and appends to translationUpdates.sql',
)]
class TranslationFillMissingCommand extends Command
{
    private const SQL_FILE = 'translationUpdates.sql';

    public function __construct(
        private readonly TranslationFileManager $fileManager,
        private readonly LanguageService $languageService,
        private readonly CommandService $commandService,
        private readonly string $kernelProjectDir,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Show what would be added without actually writing to file',
        );
        $this->addOption(
            'auto-translate',
            null,
            InputOption::VALUE_NONE,
            'Automatically generate basic translations for missing entries',
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $autoTranslate = $input->getOption('auto-translate');

        // Extract latest translations from templates
        $this->commandService->extractTranslations();

        $missingTranslations = $this->getMissingTranslations();

        if (empty($missingTranslations)) {
            $io->success('No missing translations found!');
            return Command::SUCCESS;
        }

        $io->section(sprintf('Found %d translation keys with missing values', count($missingTranslations)));

        $sqlStatements = [];
        $skippedKeys = [];

        foreach ($missingTranslations as $entry) {
            $key = $entry['placeholder'];
            $de = $entry['de'] ?? '';
            $en = $entry['en'] ?? '';
            $cn = $entry['cn'] ?? '';

            // Check if we can auto-translate
            if ($autoTranslate && $this->canAutoTranslate($entry)) {
                [$de, $en, $cn] = $this->autoTranslate($key, $de, $en, $cn);
            }

            // Skip if still missing critical translations
            if (empty($de) && empty($en)) {
                $skippedKeys[] = $key;
                continue;
            }

            // Generate SQL statements
            $sqlStatements[] = $this->generateSqlForKey($key, $de, $en, $cn);
        }

        if (!empty($skippedKeys)) {
            $io->warning(sprintf(
                'Skipped %d keys that need manual translation: %s',
                count($skippedKeys),
                implode(', ', array_slice($skippedKeys, 0, 5)) . (count($skippedKeys) > 5 ? '...' : ''),
            ));
        }

        if (empty($sqlStatements)) {
            $io->warning('No translations could be generated automatically. Manual translation needed.');
            return Command::FAILURE;
        }

        // Preview or write SQL
        if ($dryRun) {
            $io->section('Preview (dry-run mode):');
            $io->writeln(implode("\n", $sqlStatements));
            $io->info(sprintf('Would add %d translation keys', count($sqlStatements)));
        } else {
            $sqlFile = $this->kernelProjectDir . '/' . self::SQL_FILE;
            $content = implode("\n", $sqlStatements) . "\n";

            file_put_contents($sqlFile, $content, FILE_APPEND);

            $io->success([
                sprintf('Added %d translation keys to %s', count($sqlStatements), self::SQL_FILE),
                'Total SQL statements: ' . (count($sqlStatements) * 3), // 3 languages
            ]);
        }

        if (!empty($skippedKeys)) {
            $io->note([
                'Some keys require manual translation.',
                'Run without --auto-translate to review them individually.',
            ]);
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<array{placeholder: string, de: string, en: string, cn: string}>
     */
    private function getMissingTranslations(): array
    {
        $locales = $this->languageService->getEnabledCodes();
        $translationsByLocale = [];

        foreach ($locales as $locale) {
            $translationsByLocale[$locale] = [];
        }

        foreach ($this->fileManager->getTranslationFiles() as $file) {
            $parts = explode('.', (string) $file->getFilename());
            if (count($parts) !== 3) {
                continue;
            }
            [$name, $lang, $ext] = $parts;

            if (!in_array($lang, $locales, true)) {
                continue;
            }

            $translations = include $file->getPathname();
            if (is_array($translations)) {
                $translationsByLocale[$lang] = $translations;
            }
        }

        // Get all unique placeholders
        $placeholders = [];
        foreach ($translationsByLocale as $translations) {
            foreach (array_keys($translations) as $placeholder) {
                $placeholders[$placeholder] = true;
            }
        }
        $placeholders = array_keys($placeholders);
        sort($placeholders);

        $result = [];
        foreach ($placeholders as $placeholder) {
            $entry = ['placeholder' => $placeholder];
            $isMissing = false;
            foreach ($locales as $locale) {
                $value = $translationsByLocale[$locale][$placeholder] ?? '';
                $entry[$locale] = $value;
                if ($value === '') {
                    $isMissing = true;
                }
            }

            if ($isMissing) {
                $result[] = $entry;
            }
        }

        return $result;
    }

    /**
     * @param array{placeholder: string, de: string, en: string, cn: string} $entry
     */
    private function canAutoTranslate(array $entry): bool
    {
        // Can auto-translate if at least one language is provided
        return !empty($entry['de']) || !empty($entry['en']) || !empty($entry['cn']);
    }

    /**
     * Auto-generate missing translations using simple heuristics.
     *
     * @return array{0: string, 1: string, 2: string} [de, en, cn]
     */
    private function autoTranslate(string $key, string $de, string $en, string $cn): array
    {
        // Basic translation mappings for common terms
        $commonTranslations = [
            'de' => [
                'Config' => 'Konfiguration',
                'Settings' => 'Einstellungen',
                'Images' => 'Bilder',
                'Theme' => 'Design',
                'Templates' => 'Vorlagen',
                'Debugging' => 'Debugging',
                'Announcements' => 'Ankündigungen',
                'Send Log' => 'Sendeprotokoll',
                'Login now' => 'Jetzt anmelden',
                'Home page' => 'Startseite',
                'Normal' => 'Normal',
            ],
            'en' => [
                'Konfiguration' => 'Config',
                'Einstellungen' => 'Settings',
                'Bilder' => 'Images',
                'Design' => 'Theme',
                'Vorlagen' => 'Templates',
                'Debugging' => 'Debugging',
                'Ankündigungen' => 'Announcements',
                'Sendeprotokoll' => 'Send Log',
                'Jetzt anmelden' => 'Login now',
                'Startseite' => 'Home page',
                'Normal' => 'Normal',
            ],
            'cn' => [
                'Config' => '配置',
                'Settings' => '设置',
                'Images' => '图片',
                'Theme' => '主题',
                'Templates' => '模板',
                'Debugging' => '调试',
                'Announcements' => '公告',
                'Send Log' => '发送日志',
                'Login now' => '立即登录',
                'Home page' => '首页',
                'Normal' => '正常',
            ],
        ];

        // Try to fill missing translations using mappings
        if (empty($de) && !empty($en)) {
            $de = $commonTranslations['de'][$en] ?? '';
        }
        if (empty($en) && !empty($de)) {
            $en = $commonTranslations['en'][$de] ?? '';
        }
        if (empty($cn) && !empty($en)) {
            $cn = $commonTranslations['cn'][$en] ?? '';
        }

        // If still empty for Chinese but have English, use a placeholder
        if (empty($cn) && !empty($en)) {
            $cn = ''; // Leave empty - requires manual translation
        }

        return [$de, $en, $cn];
    }

    private function generateSqlForKey(string $key, string $de, string $en, string $cn): string
    {
        $escapedKey = $this->escapeSql($key);
        $escapedDe = $this->escapeSql($de);
        $escapedEn = $this->escapeSql($en);
        $escapedCn = $this->escapeSql($cn);

        $sql = "-- Translation key: {$key}\n";

        if (!empty($de)) {
            $sql .= "INSERT INTO translation (created_at, language, placeholder, translation, user_id) VALUES (NOW(), 'de', '{$escapedKey}', '{$escapedDe}', 1) ON DUPLICATE KEY UPDATE translation = '{$escapedDe}', created_at = NOW();\n";
        }
        if (!empty($en)) {
            $sql .= "INSERT INTO translation (created_at, language, placeholder, translation, user_id) VALUES (NOW(), 'en', '{$escapedKey}', '{$escapedEn}', 1) ON DUPLICATE KEY UPDATE translation = '{$escapedEn}', created_at = NOW();\n";
        }
        if (!empty($cn)) {
            $sql .= "INSERT INTO translation (created_at, language, placeholder, translation, user_id) VALUES (NOW(), 'cn', '{$escapedKey}', '{$escapedCn}', 1) ON DUPLICATE KEY UPDATE translation = '{$escapedCn}', created_at = NOW();\n";
        }

        return $sql . "\n";
    }

    private function escapeSql(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
