<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\TranslationSuggestionStatus;
use App\Repository\TranslationSuggestionRepository;
use App\Service\DashboardService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AttentionExtension extends AbstractExtension
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly TranslationSuggestionRepository $translationSuggestionRepo,
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
        return count($this->dashboardService->getNeedForApproval()) > 0;
    }

    public function getManagerAttention(): bool
    {
        return count($this->translationSuggestionRepo->findBy(['status' => TranslationSuggestionStatus::Requested])) > 0;
    }
}
