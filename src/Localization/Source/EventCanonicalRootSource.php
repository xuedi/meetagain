<?php declare(strict_types=1);

namespace App\Localization\Source;

use App\Entity\EventCanonicalRoot;
use App\Localization\AbstractLocalizedRowSource;
use App\Localization\LocalizedContentRow;
use Override;

final readonly class EventCanonicalRootSource extends AbstractLocalizedRowSource
{
    #[Override]
    public function getKey(): string
    {
        return 'event_canonical_root';
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'localized_content.source_event_canonical_root';
    }

    #[Override]
    public function getOwnerType(): string
    {
        return self::OWNER_EVENT;
    }

    #[Override]
    public function findOutsideLocales(array $ownerIds, array $keepLocales): array
    {
        $rows = [];
        /** @var EventCanonicalRoot $marker */
        foreach ($this->fetchEntities($ownerIds, $keepLocales) as $marker) {
            $event = $marker->getEvent();
            $rows[] = new LocalizedContentRow(
                sourceKey: $this->getKey(),
                ownerId: (int) $event?->getId(),
                locale: $marker->getLocale(),
                ownerLabel: $event?->getStart()->format('Y-m-d') ?? '',
                preview: $marker->getType()->value,
            );
        }

        return $rows;
    }

    #[Override]
    protected function getEntityClass(): string
    {
        return EventCanonicalRoot::class;
    }

    #[Override]
    protected function getLocaleField(): string
    {
        return 'locale';
    }

    #[Override]
    protected function getOwnerField(): string
    {
        return 'event';
    }
}
