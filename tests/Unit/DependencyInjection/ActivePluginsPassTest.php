<?php declare(strict_types=1);

namespace Tests\Unit\DependencyInjection;

use App\Command\CronCommand;
use App\DependencyInjection\ActivePluginMembers;
use App\DependencyInjection\ActivePluginsPass;
use App\Item\TypeRegistry;
use App\Kernel;
use PHPUnit\Framework\TestCase;
use Plugin\Books\Service\ConfigService;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class ActivePluginsPassTest extends TestCase
{
    public function testACoreChainWithAPluginMemberIsWrappedWithThatMembersPlugin(): void
    {
        // Arrange
        $container = $this->container(CronCommand::class, ['core.member', 'books.member', 'films.member']);

        // Act
        new ActivePluginsPass()->process($container);

        // Assert
        $argument = $container->getDefinition('consumer')->getArgument(0);
        static::assertInstanceOf(Definition::class, $argument);
        static::assertSame(ActivePluginMembers::class, $argument->getClass());
        static::assertSame([1 => 'books', 2 => 'films'], $argument->getArgument(1));
    }

    public function testAChainOfCoreMembersOnlyIsLeftAlone(): void
    {
        // Arrange
        $container = $this->container(CronCommand::class, ['core.member']);

        // Act
        new ActivePluginsPass()->process($container);

        // Assert
        static::assertInstanceOf(IteratorArgument::class, $container->getDefinition('consumer')->getArgument(0));
    }

    public function testAConsumerThatSeesInactivePluginsIsLeftAlone(): void
    {
        // Arrange
        $container = $this->container(TypeRegistry::class, ['books.member']);

        // Act
        new ActivePluginsPass()->process($container);

        // Assert
        static::assertInstanceOf(IteratorArgument::class, $container->getDefinition('consumer')->getArgument(0));
    }

    public function testAPluginsOwnMembersAreNotFilteredForItself(): void
    {
        // Arrange
        $container = $this->container(ConfigService::class, ['books.member', 'films.member']);

        // Act
        new ActivePluginsPass()->process($container);

        // Assert
        static::assertSame([1 => 'films'], $container->getDefinition('consumer')->getArgument(0)->getArgument(1));
    }

    /** @param list<string> $members */
    private function container(string $consumerClass, array $members): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(Kernel::PLUGINS_PARAMETER, ['books' => [], 'films' => []]);
        $container->register('core.member', 'App\Example\CoreMember');
        $container->register('books.member', 'Plugin\Books\Example\Member');
        $container->register('films.member', 'Plugin\Films\Example\Member');
        $container
            ->register('consumer', $consumerClass)
            ->setArguments([
                new IteratorArgument(array_map(static fn(string $id): Reference => new Reference($id), $members)),
            ]);

        return $container;
    }
}
