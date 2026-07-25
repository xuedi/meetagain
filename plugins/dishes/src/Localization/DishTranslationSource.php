<?php declare(strict_types=1);

namespace Plugin\Dishes\Localization;

use App\Localization\AbstractLocalizedRowSource;
use App\Localization\LocalizedContentRow;
use Override;
use Plugin\Dishes\Entity\DishTranslation;
use Plugin\Dishes\Service\DishService;

final readonly class DishTranslationSource extends AbstractLocalizedRowSource
{
    #[Override]
    public function getKey(): string
    {
        return 'dish_translation';
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'dishes.localized_content_source';
    }

    #[Override]
    public function getOwnerType(): string
    {
        return DishService::ITEM_TYPE;
    }

    #[Override]
    public function findOutsideLocales(array $ownerIds, array $keepLocales): array
    {
        $rows = [];
        /** @var DishTranslation $translation */
        foreach ($this->fetchEntities($ownerIds, $keepLocales) as $translation) {
            $dish = $translation->getDish();
            $rows[] = new LocalizedContentRow(
                sourceKey: $this->getKey(),
                ownerId: (int) $dish?->getId(),
                locale: (string) $translation->getLanguage(),
                ownerLabel: $this->dishLabel($translation, $keepLocales),
                preview: $this->preview($translation->getName()),
            );
        }

        return $rows;
    }

    #[Override]
    protected function getEntityClass(): string
    {
        return DishTranslation::class;
    }

    #[Override]
    protected function getLocaleField(): string
    {
        return 'language';
    }

    #[Override]
    protected function getOwnerField(): string
    {
        return 'dish';
    }

    /**
     * @param list<string> $keepLocales
     */
    private function dishLabel(DishTranslation $translation, array $keepLocales): string
    {
        $dish = $translation->getDish();
        if ($dish === null) {
            return '';
        }

        foreach ($dish->getTranslations() as $candidate) {
            if (in_array($candidate->getLanguage(), $keepLocales, true)) {
                return (string) $candidate->getName();
            }
        }

        return '#' . $dish->getId();
    }
}
