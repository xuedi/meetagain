<?php declare(strict_types=1);

namespace App\DataHotfix\Hotfixes;

use App\DataHotfix\DataHotfixInterface;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use App\Service\Config\PluginService;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class PluginActivationInitialState implements DataHotfixInterface
{
    public function __construct(
        private PluginService $pluginService,
        private UserRepository $userRepository,
        #[Autowire('%kernel.project_dir%/config/plugins.php')]
        private string $legacyConfigFile,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return '2026_10_18_plugin_activation_initial_state';
    }

    #[Override]
    public function execute(): void
    {
        if ($this->pluginService->hasState()) {
            return;
        }

        if (is_file($this->legacyConfigFile)) {
            $legacy = require $this->legacyConfigFile;
            $state = [];
            foreach (is_array($legacy) ? $legacy : [] as $pluginKey => $enabled) {
                $state[(string) $pluginKey] = $enabled === true;
            }
            $this->pluginService->adopt($state);

            return;
        }

        if ($this->userRepository->count(['role' => UserRole::Admin]) > 0) {
            $this->pluginService->adopt(array_fill_keys($this->pluginService->getInstalledKeys(), true));
        }
    }
}
