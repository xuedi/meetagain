<?php declare(strict_types=1);

namespace App\Service;

use App\Plugin;
use App\Service\Command\ClearCacheCommand;
use App\Service\Command\CommandInterface;
use App\Service\Command\ExecuteMigrationsCommand;
use App\Service\Command\ExtractTranslationsCommand;
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;

readonly class CommandService
{
    public function __construct(
        private KernelInterface $kernel,
        private LanguageService $languageService,
    ) {
    }

    public function execute(CommandInterface $command): string
    {
        $input = new ArrayInput($command->getParameter());
        $output = new BufferedOutput();

        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $application->run($input, $output);

        $converter = new AnsiToHtmlConverter();
        $content = $output->fetch();

        return $converter->convert($content);
    }

    public function clearCache(): string
    {
        return $this->execute(new ClearCacheCommand());
    }

    public function executeMigrations(): string
    {
        return $this->execute(new ExecuteMigrationsCommand()) . PHP_EOL;
    }

    public function extractTranslations(): string
    {
        $output = '';
        foreach ($this->languageService->getEnabledCodes() as $locale) {
            $output .= $this->execute(new ExtractTranslationsCommand($locale)) . PHP_EOL;
        }

        return $output;
    }
}
