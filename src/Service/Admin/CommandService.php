<?php declare(strict_types=1);

namespace App\Service\Admin;

use App\Service\Command\ClearCacheCommand;
use App\Service\Command\CommandInterface;
use App\Service\Command\ExecuteMigrationsCommand;
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;

readonly class CommandService
{
    private Application $application;
    private AnsiToHtmlConverter $converter;

    public function __construct(
        private KernelInterface $kernel,
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

    public function executeSubprocessMigrations(): void
    {
        $projectDir = $this->kernel->getProjectDir();
        $process = new Process([
            'php',
            $projectDir . '/bin/console',
            'doctrine:migrations:migrate',
            '--no-interaction',
            '--env=' . $this->kernel->getEnvironment(),
        ]);
        $process->setWorkingDirectory($projectDir);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Migration failed: ' . $process->getErrorOutput());
        }
    }
}
