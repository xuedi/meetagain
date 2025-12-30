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
    private Application $application;
    private AnsiToHtmlConverter $converter;

    public function __construct(
        private KernelInterface $kernel,
        private LanguageService $languageService,
    ) {
        $this->application = new Application($this->kernel);
        $this->application->setAutoExit(false);
        $this->converter = new AnsiToHtmlConverter();
    }

    public function execute(CommandInterface $command): string
    {
        $params = $command->getParameter();
        $commandName = $command->getCommand();
        if (!isset($params['command'])) {
            $params['command'] = $commandName;
        }

        return $this->run($params);
    }

    public function run(array $parameters): string
    {
        $input = new ArrayInput($parameters);
        $output = new BufferedOutput();

        $this->application->run($input, $output);

        return $this->converter->convert($output->fetch());
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
