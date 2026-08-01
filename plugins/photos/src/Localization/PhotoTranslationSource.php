<?php declare(strict_types=1);

namespace Plugin\Photos\Localization;

use App\Localization\AbstractLocalizedRowSource;
use App\Localization\LocalizedContentRow;
use Override;
use Plugin\Photos\Entity\PhotoTranslation;
use Plugin\Photos\Service\PhotoService;

final readonly class PhotoTranslationSource extends AbstractLocalizedRowSource
{
    #[Override]
    public function getKey(): string
    {
        return 'photo_translation';
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'photos.localized_content_source';
    }

    #[Override]
    public function getOwnerType(): string
    {
        return PhotoService::ITEM_TYPE;
    }

    #[Override]
    public function findOutsideLocales(array $ownerIds, array $keepLocales): array
    {
        $rows = [];
        /** @var PhotoTranslation $translation */
        foreach ($this->fetchEntities($ownerIds, $keepLocales) as $translation) {
            $photo = $translation->getPhoto();
            $rows[] = new LocalizedContentRow(
                sourceKey: $this->getKey(),
                ownerId: (int) $photo?->getId(),
                locale: (string) $translation->getLanguage(),
                ownerLabel: $this->photoLabel($translation, $keepLocales),
                preview: $this->preview($translation->getTitle()),
            );
        }

        return $rows;
    }

    #[Override]
    protected function getEntityClass(): string
    {
        return PhotoTranslation::class;
    }

    #[Override]
    protected function getLocaleField(): string
    {
        return 'language';
    }

    #[Override]
    protected function getOwnerField(): string
    {
        return 'photo';
    }

    /**
     * @param list<string> $keepLocales
     */
    private function photoLabel(PhotoTranslation $translation, array $keepLocales): string
    {
        $photo = $translation->getPhoto();
        if ($photo === null) {
            return '';
        }

        foreach ($photo->getTranslations() as $candidate) {
            if (in_array($candidate->getLanguage(), $keepLocales, true)) {
                return (string) $candidate->getTitle();
            }
        }

        return '#' . $photo->getId();
    }
}
