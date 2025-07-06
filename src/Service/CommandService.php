<?php declare(strict_types=1);

namespace App\Service;

use SensioLabs\AnsiConverter\AnsiToHtmlConverter;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\HttpKernel\KernelInterface;

readonly class CommandService
{
    public function __construct(private KernelInterface $kernel, private string $kernelProjectDir)
    {
    }

    public function clearCache(): string
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $output = new BufferedOutput();
        $input = new ArrayInput([]);

        $command = $application->find('cache:clear');
        $command->run($input, $output);

        $converter = new AnsiToHtmlConverter();
        $content = $output->fetch();

        return $converter->convert($content);
    }

    public function executeMigrations(string $name): string
    {
        define('STDIN', fopen("php://stdin", "r"));

        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $config = sprintf('%s/plugins/%s/Config/packages/migration/config.yaml', $this->kernelProjectDir, $name);
        $arguments = [
            '--configuration' => $config,
            '--em' => sprintf('em%s', $name),
            '--no-interaction' => true,
        ];

        $output = new BufferedOutput();
        $input = new ArrayInput($arguments);

        $command = $application->find('doctrine:migrations:migrate');
        $command->run($input, $output);

        return $output->fetch();
    }
}
