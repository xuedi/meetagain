<?php declare(strict_types=1);

namespace Module\Circulation\Internal\Twig;

use App\Entity\User;
use Module\Circulation\Internal\CirculationService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class Runtime implements RuntimeExtensionInterface
{
    public const string BADGE_TEMPLATE = '@Circulation/components/badge.html.twig';
    public const string PANEL_TEMPLATE = '@Circulation/components/panel.html.twig';

    public function __construct(
        private CirculationService $circulation,
        private Security $security,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

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
