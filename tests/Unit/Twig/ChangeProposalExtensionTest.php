<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Review\ChangeProposalService;
use App\Twig\ChangeProposalExtension;
use PHPUnit\Framework\TestCase;

class ChangeProposalExtensionTest extends TestCase
{
    public function testPendingCountDelegatesToTheService(): void
    {
        // Arrange
        $service = $this->createMock(ChangeProposalService::class);
        $service->expects(self::once())
            ->method('countPendingForTarget')
            ->with('glossary', 4)
            ->willReturn(2);
        $extension = new ChangeProposalExtension($service);

        // Act
        $count = $extension->pendingCount('glossary', 4);

        // Assert
        self::assertSame(2, $count);
    }
}
