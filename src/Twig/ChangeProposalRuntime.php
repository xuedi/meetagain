<?php declare(strict_types=1);

namespace App\Twig;

use App\Review\ChangeProposalService;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class ChangeProposalRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private ChangeProposalService $service,
    ) {}

    public function pendingCount(string $targetType, int $targetId): int
    {
        return $this->service->countPendingForTarget($targetType, $targetId);
    }
}
