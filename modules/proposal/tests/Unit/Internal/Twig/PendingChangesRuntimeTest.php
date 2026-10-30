<?php declare(strict_types=1);

namespace Module\Proposal\Tests\Unit\Internal\Twig;

use Module\Proposal\Internal\Change\ChangeService;
use Module\Proposal\Internal\Twig\PendingChangesRuntime;
use PHPUnit\Framework\TestCase;

class PendingChangesRuntimeTest extends TestCase
{
    public function testPendingCountDelegatesToTheService(): void
    {
        // Arrange
        $service = $this->createMock(ChangeService::class);
        $service->expects(self::once())->method('countPending')->with('glossary', 4)->willReturn(2);
        $runtime = new PendingChangesRuntime($service);

        // Act
        $count = $runtime->pendingCount('glossary', 4);

        // Assert
        self::assertSame(2, $count);
    }
}
