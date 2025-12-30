<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\DashboardActionService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AttentionExtension extends AbstractExtension
{
    public function __construct(
        private readonly DashboardActionService $dashboardAction,
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('has_admin_attention', $this->getAdminAttention(...)),
            new TwigFunction('has_manager_attention', $this->getManagerAttention(...)),
        ];
    }

    public function getAdminAttention(): bool
    {
        return count($this->dashboardAction->getNeedForApproval()) > 0;
    }

    public function getManagerAttention(): bool
    {
        return $this->dashboardAction->getPendingSuggestionsCount() > 0;
    }
}
