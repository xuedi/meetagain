<?php declare(strict_types=1);

namespace Plugin\Karaoke\Localization;

use App\Localization\AbstractLocalizedRowSource;
use App\Localization\LocalizedContentRow;
use Override;
use Plugin\Karaoke\Entity\LineTranslation;
use Plugin\Karaoke\Service\SongService;

final readonly class LineTranslationSource extends AbstractLocalizedRowSource
{
    #[Override]
    public function getKey(): string
    {
        return 'karaoke_line_translation';
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'karaoke.localized_content_source';
    }

    #[Override]
    public function getOwnerType(): string
    {
        return SongService::ITEM_TYPE;
    }

    #[Override]
    public function findOutsideLocales(array $ownerIds, array $keepLocales): array
    {
        $rows = [];
        /** @var LineTranslation $translation */
        foreach ($this->fetchEntities($ownerIds, $keepLocales) as $translation) {
            $song = $translation->getLine()?->getSong();
            $rows[] = new LocalizedContentRow(
                sourceKey: $this->getKey(),
                ownerId: (int) $song?->getId(),
                locale: (string) $translation->getLanguage(),
                ownerLabel: (string) $song?->getTitle(),
                preview: $this->preview($translation->getText()),
            );
        }

        return $rows;
    }

    #[Override]
    protected function getEntityClass(): string
    {
        return LineTranslation::class;
    }

    #[Override]
    protected function getLocaleField(): string
    {
        return 'language';
    }

    #[Override]
    protected function getOwnerField(): string
    {
        return 'line.song';
    }
}
