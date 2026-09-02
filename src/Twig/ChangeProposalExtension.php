<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ChangeProposalExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('pending_change_proposals', [ChangeProposalRuntime::class, 'pendingCount']),
        ];
    }
}
