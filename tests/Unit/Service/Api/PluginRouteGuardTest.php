<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\Api;

use App\Service\Api\PluginRouteGuard;
use App\Service\Config\ActivePluginListInterface;
use Override;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @covers \App\Service\Api\PluginRouteGuard
 */
final class PluginRouteGuardTest extends TestCase
{
    public function testRequireActivePassesWhenKeyIsInActiveList(): void
    {
        // Arrange
        $guard = new PluginRouteGuard(new GuardActivePluginList(['glossary', 'alpha']));

        // Act
        $guard->requireActive('glossary');

        // Assert - no exception thrown
        static::assertTrue(true);
    }

    public function testRequireActiveThrowsWhenKeyIsAbsent(): void
    {
        // Arrange
        $guard = new PluginRouteGuard(new GuardActivePluginList(['glossary']));

        // Act + Assert
        $this->expectException(NotFoundHttpException::class);
        $guard->requireActive('dinnerclub');
    }

    public function testIsActiveReturnsTrueForActiveKey(): void
    {
        // Arrange
        $guard = new PluginRouteGuard(new GuardActivePluginList(['filmclub']));

        // Act + Assert
        static::assertTrue($guard->isActive('filmclub'));
        static::assertFalse($guard->isActive('bookclub'));
    }
}

final readonly class GuardActivePluginList implements ActivePluginListInterface
{
    /**
     * @param array<string> $keys
     */
    public function __construct(private array $keys) {}

    #[Override] public function getActiveList(): array
    {
        return $this->keys;
    }
}
