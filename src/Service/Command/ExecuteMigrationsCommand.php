<?php declare(strict_types=1);

namespace App\Service\Command;

readonly class ExecuteMigrationsCommand implements CommandInterface
{
    public function __construct(private string $pluginName, private string $kernelProjectDir)
    {
        //
    }

    public function getCommand(): string
    {
        return 'doctrine:migrations:migrate';
    }

    public function getParameter(): array
    {
        $config = sprintf(
            '%s/plugins/%s/Config/packages/migration/config.yaml',
            $this->kernelProjectDir,
            $this->pluginName
        );
        return [
            'command' => $this->getCommand(),
            '--configuration' => $config,
            '--em' => sprintf('em%s', $this->pluginName),
            '--no-interaction' => true,
        ];
    }
}
