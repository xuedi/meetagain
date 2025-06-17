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
        $list = $this->pluginRepo->findAllWithIdentKey();
        foreach ($this->plugins as $plugin) {
            $ident = $plugin->getIdent();
            if (isset($list[$ident])) {
                $list[$ident]->setDeleted(false);
                continue;
            }
            $newPlugin = new Plugin();
            $newPlugin->setIdent($ident);
            $newPlugin->setName($plugin->getName());
            $newPlugin->setVersion($plugin->getVersion());
            $newPlugin->setDescription($plugin->getDescription());
            $newPlugin->setDeleted(false);
            $newPlugin->setInstalled(false);
            $newPlugin->setEnabled(false);

            $list[$ident] = $newPlugin;
        }
        ksort($list);

        return $list;
    }

    public function remove(int $id): void
    {
        $this->em->remove($this->pluginRepo->find($id));
        $this->em->flush();
    }

    public function install(string $ident): void
    {
        $plugin = $this->getPlugin($ident);
        $plugin->install();

        $pluginEntity = new Plugin();
        $pluginEntity->setIdent($ident);
        $pluginEntity->setName($plugin->getName());
        $pluginEntity->setVersion($plugin->getVersion());
        $pluginEntity->setDescription($plugin->getDescription());
        $pluginEntity->setInstalled(true);
        $pluginEntity->setEnabled(false);

        $this->em->persist($pluginEntity);
        $this->em->flush();

        // TODO: run local plugin migrations and so on
    }

    public function uninstall(string $ident): void
    {
        $plugin = $this->getPlugin($ident);
        $plugin->uninstall();

        $this->em->remove($this->pluginRepo->findOneBy(['ident' => $ident]));
        $this->em->flush();
    }

    private function getPlugin(string $ident): PluginInterface
    {
        foreach ($this->plugins as $plugin) {
            if ($plugin->getIdent() === $ident) {
                return $plugin;
            }
        }
        throw new Exception('Plugin not found');
    }

    public function enable(string $ident): void
    {
        $pluginEntity = $this->pluginRepo->findOneBy(['ident' => $ident]);
        $pluginEntity->setEnabled(true);

        $this->em->persist($pluginEntity);
        $this->em->flush();

        $this->generatePluginConfig();
    }

    public function disable(string $ident): void
    {
        $pluginEntity = $this->pluginRepo->findOneBy(['ident' => $ident]);
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
}
