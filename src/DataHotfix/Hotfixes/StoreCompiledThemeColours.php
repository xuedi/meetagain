<?php declare(strict_types=1);

namespace App\DataHotfix\Hotfixes;

use App\DataHotfix\DataHotfixInterface;
use App\Theme\Colors;
use App\Theme\Descriptor;
use Override;

readonly class StoreCompiledThemeColours implements DataHotfixInterface
{
    public function __construct(
        private Colors $colors,
        private Descriptor $descriptor,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return '2026_10_17_store_compiled_theme_colours';
    }

    #[Override]
    public function execute(): void
    {
        if ($this->colors->hasStoredTheme()) {
            return;
        }

        $compiled = $this->descriptor->compiledBaseline()->toArray();
        if ($compiled === $this->descriptor->distributedDefaults()->toArray()) {
            return;
        }

        $this->colors->save($compiled);
    }
}
