<?php declare(strict_types=1);

namespace App\Command;

use App\Service\CommandService;
use App\Service\LanguageService;
use App\Service\TranslationFileManager;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:translation:missing', description: 'Lists all missing translations in a structured JSON format')]
class TranslationListCommand extends Command
{
    public function __construct(
        private readonly TranslationFileManager $fileManager,
        private readonly LanguageService $languageService,
        private readonly CommandService $commandService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->commandService->extractTranslations();

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

        $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return Command::SUCCESS;
    }
}
