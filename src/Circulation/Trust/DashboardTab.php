<?php declare(strict_types=1);

namespace App\Circulation\Trust;

use App\Circulation\DashboardTabInterface;
use Override;
use Twig\Environment;

final readonly class DashboardTab implements DashboardTabInterface
{
    public const string KEY = 'trust';
    public const string TEMPLATE = 'circulation/tabs/trust.html.twig';

    public function __construct(
        private ContextIndex $index,
        private Environment $twig,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return self::KEY;
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'circulation.trust_tab_label';
    }

    #[Override]
    public function getIcon(): string
    {
        return 'fa-award';
    }

    #[Override]
    public function supports(string $itemType, string $context): bool
    {
        return $this->index->itemTypeFor($context) === $itemType;
    }

    #[Override]
    public function render(string $itemType, string $context): string
    {
        return $this->twig->render(self::TEMPLATE, ['context' => $context]);
    }

    #[Override]
    public function getPriority(): int
    {
        return -10;
    }
}
