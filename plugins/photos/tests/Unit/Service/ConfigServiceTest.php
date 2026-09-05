<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Unit\Service;

use App\Publisher\PluginSettings\Resolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Photos\Service\ConfigService;
use Plugin\Photos\ValueObject\Config;

class ConfigServiceTest extends TestCase
{
    public function testReturnsResolvedConfig(): void
    {
        // Arrange
        $config = new Config()->setMemberUploads(false);
        $resolver = $this->createStub(Resolver::class);
        $resolver->method('resolve')->willReturn($config);
        $service = new ConfigService($resolver, $this->createStub(AuthorizationCheckerInterface::class));

        // Act + Assert
        static::assertSame($config, $service->getConfig());
    }

    public function testMemoizesResolvedConfig(): void
    {
        // Arrange
        $resolver = $this->createMock(Resolver::class);
        $resolver->expects(static::once())->method('resolve')->willReturn(new Config());
        $service = new ConfigService($resolver, $this->createStub(AuthorizationCheckerInterface::class));

        // Act
        $first = $service->getConfig();
        $second = $service->getConfig();

        // Assert
        static::assertSame($first, $second);
    }
}
