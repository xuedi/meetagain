<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\DashboardActionService;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AttentionExtension extends AbstractExtension
{
    public function __construct(
        private readonly DashboardActionService $dashboardAction,
    ) {
    }

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('has_admin_attention', $this->getAdminAttention(...)),
        ];
    }

    public function getAdminAttention(): bool
    {
        return count($this->dashboardAction->getNeedForApproval()) > 0;
    }
}
