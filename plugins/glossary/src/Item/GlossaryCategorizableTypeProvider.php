<?php declare(strict_types=1);

namespace Plugin\Glossary\Item;

use App\Item\Taxonomy\CategorizableTypeProviderInterface;
use App\Item\Taxonomy\Config;
use Override;
use Plugin\Glossary\Service\ConfigService;

final readonly class GlossaryCategorizableTypeProvider implements CategorizableTypeProviderInterface
{
    public const string ITEM_TYPE = 'glossary';

    public function __construct(
        private ConfigService $configService,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'glossary';
    }

    #[Override]
    public function getTypeKey(): string
    {
        return self::ITEM_TYPE;
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'glossary.menu_main';
    }

    #[Override]
    public function supportsCategories(): bool
    {
        return true;
    }

    #[Override]
    public function supportsTags(): bool
    {
        return false;
    }

    #[Override]
    public function getTaxonomy(): Config
    {
        return $this->configService->getConfig()->getTaxonomy();
    }
}
