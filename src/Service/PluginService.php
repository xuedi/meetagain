<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Plugin;
use App\Plugin as PluginInterface;
use App\Repository\PluginRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpKernel\KernelInterface;

readonly class PluginService
{
    public function __construct(
        #[AutowireIterator(PluginInterface::class)]
        private iterable $plugins,
        private PluginRepository $pluginRepo,
        private EntityManagerInterface $em,
        private KernelInterface $kernel,
    )
    {
    }

    public function getAdminList(): array
    {
        $list = $this->pluginRepo->findAllWithNameKey();
        foreach ($this->plugins as $plugin) {
            dump($plugin);
            $name = $plugin->getName();
            if (isset($list[$name])) {
                $list[$name]->setDeleted(false);
                continue;
            }
            $newPlugin = new Plugin();
            $newPlugin->setName($plugin->getName());
            $newPlugin->setVersion($plugin->getVersion());
            $newPlugin->setDescription($plugin->getDescription());
            $newPlugin->setDeleted(false);
            $newPlugin->setInstalled(false);
            $newPlugin->setEnabled(false);

            $list[$name] = $newPlugin;
        }
        ksort($list);

        return $list;
    }

    public function remove(int $id): void
    {
        $this->em->remove($this->pluginRepo->find($id));
        $this->em->flush();
    }

    public function install(string $name): void
    {
        $plugin = $this->getPlugin($name);
        $plugin->install();

        $pluginEntity = new Plugin();
        $pluginEntity->setName($plugin->getName());
        $pluginEntity->setVersion($plugin->getVersion());
        $pluginEntity->setDescription($plugin->getDescription());
        $pluginEntity->setInstalled(true);
        $pluginEntity->setEnabled(false);

        $this->em->persist($pluginEntity);
        $this->em->flush();

        $this->pluginMigration($name);
    }

    public function uninstall(string $name): void
    {
        $plugin = $this->getPlugin($name);
        $plugin->uninstall();

        $this->em->remove($this->pluginRepo->findOneBy(['name' => $name]));
        $this->em->flush();
    }

    private function getPlugin(string $name): PluginInterface
    {
        foreach ($this->plugins as $plugin) {
            if ($plugin->getName() === $name) {
                return $plugin;
            }
        }
        throw new Exception('Plugin not found');
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
        $plugins = $this->pluginRepo->findBy(['enabled' => true]);
        foreach ($plugins as $plugin) {
            $nameList[] = "'" . $plugin->getName() . "'";
        }
        $content = "<?php declare(strict_types=1); return [" . implode(',', $nameList) . "];";
        file_put_contents($pluginConfigFile, $content);

        $this->clearCache();
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

    private function pluginMigration(string $name): void
    {
        $input = new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            '--no-interaction' => true,
            '--em' => 'em' . $name,
        ]);

        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $application->run($input);
    }
}
