<?php declare(strict_types=1);

namespace Module\Proposal\Internal\Twig;

use Module\Proposal\Internal\Change\ChangeService;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class PendingChangesRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private ChangeService $service,
    ) {}

    public function pendingCount(string $targetType, int $targetId): int
    {
        return $this->service->countPending($targetType, $targetId);
    }
}
