<?php declare(strict_types=1);

namespace Plugin\Films\Item;

use App\Item\Taxonomy\CategorizableTypeProviderInterface;
use App\Item\Taxonomy\Config;
use Override;
use Plugin\Films\Service\ConfigService;
use Plugin\Films\Service\FilmService;
use Plugin\Films\ValueObject\Config as FilmsConfig;

final readonly class FilmCategorizableTypeProvider implements CategorizableTypeProviderInterface
{
    public function __construct(
        private ConfigService $configService,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'films';
    }

    #[Override]
    public function getTypeKey(): string
    {
        return FilmService::ITEM_TYPE;
    }

    #[Override]
    public function getSettingsKey(): string
    {
        return 'films_taxonomy';
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'films.item_label';
    }

    #[Override]
    public function supportsCategories(): bool
    {
        return true;
    }

    #[Override]
    public function supportsTags(): bool
    {
        return true;
    }

    #[Override]
    public function getTaxonomy(): Config
    {
        return $this->configService->getConfig()->getTaxonomy();
    }

    #[Override]
    public function taxonomyOf(object $settings): Config
    {
        \assert($settings instanceof FilmsConfig);

        return $settings->getTaxonomy();
    }
}
