<?php declare(strict_types=1);

namespace App\Localization\Source;

use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Localization\AbstractLocalizedRowSource;
use App\Localization\LocalizedContentRow;
use Override;

final readonly class EventTranslationSource extends AbstractLocalizedRowSource
{
    #[Override]
    public function getKey(): string
    {
        return 'event_translation';
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'localized_content.source_event_translation';
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
        /** @var EventTranslation $translation */
        foreach ($this->fetchEntities($ownerIds, $keepLocales) as $translation) {
            $event = $translation->getEvent();
            $rows[] = new LocalizedContentRow(
                sourceKey: $this->getKey(),
                ownerId: (int) $event?->getId(),
                locale: (string) $translation->getLanguage(),
                ownerLabel: $this->eventLabel($event, $keepLocales),
                preview: $this->preview($translation->getTitle()),
            );
        }

        return $rows;
    }

    #[Override]
    protected function getEntityClass(): string
    {
        return EventTranslation::class;
    }

    #[Override]
    protected function getLocaleField(): string
    {
        return 'language';
    }

    #[Override]
    protected function getOwnerField(): string
    {
        return 'event';
    }

    /**
     * @param list<string> $keepLocales
     */
    private function eventLabel(?Event $event, array $keepLocales): string
    {
        if (!$event instanceof Event) {
            return '';
        }

        foreach ($keepLocales as $locale) {
            $translation = $event->findTranslation($locale);
            if ($translation !== null) {
                return (string) $translation->getTitle();
            }
        }

        return $event->getStart()->format('Y-m-d');
    }
}
