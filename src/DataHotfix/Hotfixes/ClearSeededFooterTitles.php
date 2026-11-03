<?php declare(strict_types=1);

namespace App\DataHotfix\Hotfixes;

use App\DataHotfix\DataHotfixInterface;
use App\Service\AppStateService;
use Override;

readonly class ClearSeededFooterTitles implements DataHotfixInterface
{
    private const array SEEDED_TITLES = [
        'footer_col1_title' => 'Help',
        'footer_col2_title' => 'Platform',
        'footer_col3_title' => 'Social',
        'footer_col4_title' => 'Legal',
    ];

    public function __construct(
        private AppStateService $appState,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return '2026_11_03_clear_seeded_footer_titles';
    }

    #[Override]
    public function execute(): void
    {
        foreach (self::SEEDED_TITLES as $key => $seeded) {
            if ($this->appState->get($key) === $seeded) {
                $this->appState->remove($key);
            }
        }
    }
}
