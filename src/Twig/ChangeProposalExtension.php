<?php declare(strict_types=1);

namespace App\Twig;

use App\Review\ChangeProposalService;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig bridge for change proposals: the pending-proposal count for one target, so templates can
 * badge their surfaces without injecting core services into cell providers.
 */
final class ChangeProposalExtension extends AbstractExtension
{
    public function __construct(
        private readonly ChangeProposalService $service,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('pending_change_proposals', $this->pendingCount(...)),
        ];
    }

    public function pendingCount(string $targetType, int $targetId): int
    {
        return $this->service->countPendingForTarget($targetType, $targetId);
    }
}
