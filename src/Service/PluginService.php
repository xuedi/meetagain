<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Plugin;
use App\Plugin as PluginInterface;
use App\Repository\PluginRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpKernel\KernelInterface;

readonly class PluginService
{
    public const string PLUGIN_DIR = __DIR__ . '/../../plugins';

    public function __construct(
        #[AutowireIterator(PluginInterface::class)]
        private iterable $plugins,
        private PluginRepository $pluginRepo,
        private EntityManagerInterface $em,
        private KernelInterface $kernel,
        private string $kernelProjectDir,
    ) {
    }

    public function getAdminList(): array
    {
        $this->discoverPlugins();

        $plugins = [];
        foreach ($this->pluginRepo->findAll() as $plugin) {
            if (!file_exists(self::PLUGIN_DIR . '/' . $plugin->getName() . '/manifest.json')) {
                $plugin->setDeleted(true);
            }
            $plugins[] = $plugin;
        }

        return $plugins;
    }

    public function discoverPlugins(): void
    {
        $pluginDir = realpath(self::PLUGIN_DIR);
        $plugins = glob($pluginDir . '/*', GLOB_ONLYDIR);
        foreach ($plugins as $plugin) {
            if ($plugin === '.' || $plugin === '..') {
                continue;
            }
            $manifest = $plugin . '/manifest.json';
            if (!file_exists($manifest)) {
                continue;
            }
            $pluginData = json_decode(file_get_contents($manifest), true);
            if (!isset($pluginData['name']) || !isset($pluginData['version']) || !isset($pluginData['description'])) {
                continue;
            }
            $entity = $this->pluginRepo->findOneBy(['name' => $pluginData['name']]);
            if ($entity === null) { // new plugin entry
                $entity = new Plugin();
                $entity->setSlug(strtolower((string) $pluginData['name']));
                $entity->setName($pluginData['name']);
                $entity->setVersion($pluginData['version']);
                $entity->setDescription($pluginData['description']);
                $entity->setDeleted(false);
                $entity->setInstalled(false);
                $entity->setEnabled(false);
                $this->em->persist($entity);
                $this->em->flush();
            }
        }
    }

    public function remove(int $id): void
    {
        $this->em->remove($this->pluginRepo->find($id));
        $this->em->flush();
    }

    public function installStep1(string $name): void
    {
        $this->verifySymfonyConfig();

        $pluginEntity = $this->pluginRepo->findOneBy(['name' => $name]);
        $pluginEntity->setInstalled(true);
        $pluginEntity->setEnabled(false);

        $this->em->persist($pluginEntity);
        $this->em->flush();

        $this->generatePluginConfig(); // make available for symfony, just without routing
    }

    public function installStep2(string $name): void
    {
        // can't do this directly after writing kernel since the old kernel (no interfaces yet)
        $pluginKernel = $this->getKernel($name);
        $pluginKernel->install();

        $this->executeMigrations($name);
    }

    public function uninstall(string $name): void
    {
        $plugin = $this->getKernel($name);
        $plugin->uninstall();

        $this->em->remove($this->pluginRepo->findOneBy(['name' => $name]));
        $this->em->flush();

        $this->generatePluginConfig();
    }

    public function enable(string $name): void
    {
        $pluginEntity = $this->pluginRepo->findOneBy(['name' => $name]);
        $pluginEntity->setEnabled(true);

        $this->em->persist($pluginEntity);
        $this->em->flush();

        $this->generatePluginConfig();
    }

    public function disable(string $name): void
    {
        $pluginEntity = $this->pluginRepo->findOneBy(['name' => $name]);
        $pluginEntity->setEnabled(false);

        $this->em->persist($pluginEntity);
        $this->em->flush();

        $this->generatePluginConfig();
    }

    public function generatePluginConfig(): void
    {
        $nameList = [];
        $pluginConfigFile = __DIR__ . '/../../config/plugins.php';
        $plugins = $this->pluginRepo->findBy(['installed' => true]);
        foreach ($plugins as $plugin) {
            $nameList[] = "'" . $plugin->getName() . "' => " . ($plugin->isEnabled() ? 'true' : 'false');
        }
        $content = "<?php declare(strict_types=1); return [" . implode(',', $nameList) . "];";
        file_put_contents($pluginConfigFile, $content);

        $this->clearCache();
    }

    private function getKernel(string $name): PluginInterface
    {
        foreach ($this->plugins as $plugin) {
            if ($plugin->getName() === $name) {
                return $plugin;
            }
        }
        throw new Exception('Plugin kernel not found: ' . $name);
    }

    private function clearCache(): void
    {
        // TODO: Move into own class with the extract translations
        $input = new ArrayInput([
            'command' => 'cache:clear',
        ]);

        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $application->run($input);
    }

    private function executeMigrations(string $name): void
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

        $output = new ConsoleOutput();
        $input = new ArrayInput($arguments);

        $command = $application->find('doctrine:migrations:migrate');
        $command->run($input, $output);
    }

    private function verifySymfonyConfig(): void
    {
        // TODO: check valid yaml files, route prefix, syntax test, expected values
    }
}
