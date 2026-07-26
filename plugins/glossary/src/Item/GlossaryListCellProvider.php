<?php declare(strict_types=1);

namespace Plugin\Glossary\Item;

use App\Item\ListCellProviderInterface;
use Override;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\Service\GlossaryService;
use Twig\Environment;

final readonly class GlossaryListCellProvider implements ListCellProviderInterface
{
    public function __construct(
        private GlossaryService $glossaryService,
        private ConfigService $configService,
        private Environment $twig,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'glossary';
    }

    #[Override]
    public function getKey(): string
    {
        return GlossaryCategorizableTypeProvider::ITEM_TYPE;
    }

    #[Override]
    public function renderListCell(int $itemId): ?string
    {
        $entry = $this->glossaryService->get($itemId);
        if ($entry === null) {
            return null;
        }

        return $this->twig->render('@Glossary/item/list_cell.html.twig', [
            'entry' => $entry,
            'config' => $this->configService->getConfig(),
        ]);
    }
}
