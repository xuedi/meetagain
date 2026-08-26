<?php declare(strict_types=1);

namespace App\Twig;

use App\Circulation\CirculationService;
use App\Entity\User;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CirculationExtension extends AbstractExtension
{
    public const string BADGE_TEMPLATE = '_components/circulation/badge.html.twig';
    public const string PANEL_TEMPLATE = '_components/circulation/panel.html.twig';

    public function __construct(
        private readonly CirculationService $circulation,
        private readonly Security $security,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('circulation_enabled', $this->isEnabled(...)),
            new TwigFunction('circulation_warm', $this->warm(...)),
            new TwigFunction('circulation_badge', $this->badge(...), ['is_safe' => ['html']]),
            new TwigFunction('circulation_panel', $this->panel(...), ['is_safe' => ['html']]),
            new TwigFunction('circulation_dashboard_url', $this->dashboardUrl(...)),
        ];
    }

    public function isEnabled(string $itemType): bool
    {
        return $this->circulation->isEnabled($itemType);
    }

    /**
     * @param list<int> $itemIds
     */
    public function warm(string $itemType, array $itemIds): string
    {
        $this->circulation->warmSummaries($itemType, $itemIds, $this->viewer());

        return '';
    }

    public function badge(string $itemType, int $itemId): string
    {
        $summary = $this->circulation->getSummary($itemType, $itemId, $this->viewer());
        if ($summary === null || !$summary->hasCopies()) {
            return '';
        }

        return $this->twig->render(self::BADGE_TEMPLATE, ['summary' => $summary]);
    }

    public function panel(string $itemType, int $itemId): string
    {
        $viewer = $this->viewer();
        $summary = $this->circulation->getSummary($itemType, $itemId, $viewer);
        if ($summary === null) {
            return '';
        }

        $verdict = $viewer === null ? null : $this->circulation->canRequest($itemType, $itemId, $viewer);

        return $this->twig->render(self::PANEL_TEMPLATE, [
            'itemType' => $itemType,
            'itemId' => $itemId,
            'summary' => $summary,
            'copies' => $this->circulation->getCopies($itemType, $itemId),
            'ownRequest' => $viewer === null ? null : $this->circulation->findOwnRequest($itemType, $itemId, $viewer),
            'verdict' => $verdict,
            'viewer' => $viewer,
        ]);
    }

    public function dashboardUrl(string $itemType): ?string
    {
        if (!$this->circulation->isEnabled($itemType)) {
            return null;
        }

        return $this->urlGenerator->generate('app_circulation_dashboard', ['itemType' => $itemType]);
    }

    private function viewer(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}
