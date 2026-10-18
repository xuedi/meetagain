<?php declare(strict_types=1);

namespace App\DependencyInjection;

use App\Kernel;
use App\Service\Config\PluginService;
use Override;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\AbstractRecursivePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class ActivePluginsPass extends AbstractRecursivePass
{
    private const array CONSUMER_NAMESPACES = ['App\\', 'Module\\', 'Plugin\\'];

    protected bool $skipScalars = true;

    /** @var array<string, string> namespace prefix => plugin key */
    private array $prefixes = [];

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        $this->prefixes = [];
        foreach (array_keys((array) $container->getParameter(Kernel::PLUGINS_PARAMETER)) as $pluginKey) {
            $this->prefixes['Plugin\\' . ucfirst((string) $pluginKey) . '\\'] = (string) $pluginKey;
        }

        if ($this->prefixes !== []) {
            parent::process($container);
        }
    }

    #[Override]
    protected function processValue(mixed $value, bool $isRoot = false): mixed
    {
        if (!$value instanceof IteratorArgument || $this->currentId === null) {
            return parent::processValue($value, $isRoot);
        }

        $consumer = $this->classOf($this->currentId);
        if (!$this->isFilteredConsumer($consumer)) {
            return $value;
        }

        $consumerPlugin = $this->pluginOf($consumer);
        $pluginByKey = [];
        foreach ($value->getValues() as $key => $member) {
            $plugin = $member instanceof Reference ? $this->pluginOf($this->classOf((string) $member)) : null;
            if ($plugin !== null && $plugin !== $consumerPlugin) {
                $pluginByKey[$key] = $plugin;
            }
        }
        if ($pluginByKey === []) {
            return $value;
        }

        return new Definition(ActivePluginMembers::class, [
            $value,
            $pluginByKey,
            new ServiceClosureArgument(new Reference(PluginService::class)),
        ]);
    }

    private function isFilteredConsumer(?string $class): bool
    {
        if ($class === null || $class === PluginService::class || !class_exists($class)) {
            return false;
        }
        foreach (self::CONSUMER_NAMESPACES as $namespace) {
            if (str_starts_with($class, $namespace)) {
                return new ReflectionClass($class)->getAttributes(SeesInactivePlugins::class) === [];
            }
        }

        return false;
    }

    private function classOf(string $serviceId): ?string
    {
        if (!$this->container->has($serviceId)) {
            return null;
        }
        $class = $this->container->findDefinition($serviceId)->getClass() ?? $serviceId;

        return (string) $this->container->getParameterBag()->resolveValue($class);
    }

    private function pluginOf(?string $class): ?string
    {
        if ($class === null) {
            return null;
        }
        foreach ($this->prefixes as $prefix => $pluginKey) {
            if (str_starts_with($class, $prefix)) {
                return $pluginKey;
            }
        }

        return null;
    }
}
