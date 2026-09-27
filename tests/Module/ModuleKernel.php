<?php declare(strict_types=1);

namespace Tests\Module;

use App\Kernel;
use Override;
use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class ModuleKernel extends Kernel
{
    public const string DATABASE_SUFFIX = '_test_modules';
    private const string STUB_CONFIG = '/*/tests/config/services.yaml';
    private const string PLUGIN_STUB_CONFIG = '/*/modules/*/tests/config/services.yaml';

    #[Override]
    public function getPluginConfigDirs(): iterable
    {
        return [];
    }

    #[Override]
    public function getModuleConfigDirs(): iterable
    {
        yield from parent::getModuleConfigDirs();
        foreach (glob($this->getProjectDir() . '/plugins/*/config', GLOB_ONLYDIR) ?: [] as $pluginConfigDir) {
            yield from $this->getPluginModuleConfigDirs($pluginConfigDir);
        }
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/' . $this->environment . '_modules';
    }

    #[Override]
    public function getBuildDir(): string
    {
        return $this->getCacheDir();
    }

    #[Override]
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addResource(new GlobResource($this->getProjectDir() . '/modules', self::STUB_CONFIG, false));
        $container->addResource(new GlobResource($this->getProjectDir() . '/plugins', self::PLUGIN_STUB_CONFIG, false));
    }

    #[Override]
    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container);
        $container->import($this->getProjectDir() . '/modules' . self::STUB_CONFIG);
        $container->import($this->getProjectDir() . '/plugins' . self::PLUGIN_STUB_CONFIG);
        $container->extension('doctrine', [
            'dbal' => ['dbname_suffix' => self::DATABASE_SUFFIX . '%env(default::TEST_TOKEN)%'],
        ]);
    }
}
