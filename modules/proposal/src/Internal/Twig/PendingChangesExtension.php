<?php declare(strict_types=1);

namespace Module\Proposal\Internal\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PendingChangesExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('pending_change_proposals', [PendingChangesRuntime::class, 'pendingCount']),
        ];
    }
}
