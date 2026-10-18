<?php declare(strict_types=1);

namespace App\Command;

use App\Service\Config\PluginService;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:plugin:toggle', description: 'Switch plugins on or off; "all" names every plugin that can be switched')]
final class PluginToggleCommand extends Command
{
    public function __construct(
        private readonly PluginService $pluginService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('state', InputArgument::REQUIRED, 'on or off')->addArgument(
            'plugins',
            InputArgument::IS_ARRAY | InputArgument::REQUIRED,
            'Plugin keys, or "all"',
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $state = (string) $input->getArgument('state');
        if ($state !== 'on' && $state !== 'off') {
            $io->error('The state is "on" or "off".');

            return Command::FAILURE;
        }
        $enable = $state === 'on';

        $requested = array_values(array_filter(array_map(strval(...), (array) $input->getArgument('plugins')), static fn(string $key): bool => $key !== ''));
        $keys = in_array('all', $requested, true) ? $this->switchableKeys() : $requested;

        foreach ($keys as $key) {
            if (!$this->pluginService->isInstalled($key) || $this->pluginService->isSystem($key)) {
                $io->error(sprintf('"%s" is not a plugin that can be switched.', $key));

                return Command::FAILURE;
            }
        }

        $failures = 0;
        foreach ($this->inDependencyOrder($keys, $enable) as $key) {
            if ($this->pluginService->isEnabled($key) === $enable) {
                continue;
            }
            $blockers = $enable ? $this->pluginService->missingRequirements($key) : $this->pluginService->activeDependents($key);
            if ($blockers !== []) {
                $io->error(sprintf('%s stays %s: it %s %s.', $key, $enable ? 'off' : 'on', $enable ? 'requires' : 'is required by', implode(', ', $blockers)));
                $failures++;
                continue;
            }
            $this->pluginService->toggle($key, $enable);
            $io->writeln(sprintf('%s is %s', $key, $state));
        }

        return $failures === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function switchableKeys(): array
    {
        return array_values(array_filter($this->pluginService->getInstalledKeys(), fn(string $key): bool => !$this->pluginService->isSystem($key)));
    }

    /**
     * @param list<string> $keys
     *
     * @return list<string> requirements before their dependents when switching on, and the reverse when switching off
     */
    private function inDependencyOrder(array $keys, bool $enable): array
    {
        $requires = [];
        foreach ($this->pluginService->getAdminList() as $plugin) {
            $requires[$plugin['key']] = $plugin['requires'];
        }

        $ordered = [];
        $visit = static function (string $key) use (&$visit, &$ordered, $requires, $keys): void {
            if (in_array($key, $ordered, true)) {
                return;
            }
            foreach ($requires[$key] ?? [] as $required) {
                if (in_array($required, $keys, true)) {
                    $visit($required);
                }
            }
            $ordered[] = $key;
        };
        foreach ($keys as $key) {
            $visit($key);
        }

        return $enable ? $ordered : array_reverse($ordered);
    }
}
