<?php declare(strict_types=1);

namespace Plugin\Photos\Event;

use App\Entity\Event;
use App\Entity\ItemTag;
use App\Item\Tag\ManagedWriter;
use App\Service\Config\LanguageService;
use Plugin\Photos\Service\PhotoService;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class DateTagService
{
    public const string ROOT_LABEL_KEY = 'photos_event.tag_root';

    public function __construct(
        private ManagedWriter $managedWriter,
        private LanguageService $languageService,
        private TranslatorInterface $translator,
    ) {}

    public function assign(Event $event, int $photoId): void
    {
        $this->managedWriter->assign($this->dateTag($event), $photoId);
    }

    public function dateTag(Event $event): ItemTag
    {
        $root = $this->managedWriter->resolve(PhotoService::ITEM_TYPE, $this->rootLabels(), null);

        return $this->managedWriter->resolve(PhotoService::ITEM_TYPE, [$this->sourceLocale() => $this->date($event)], $root);
    }

    public function findDateTag(Event $event): ?ItemTag
    {
        $root = $this->managedWriter->find(PhotoService::ITEM_TYPE, $this->rootLabels(), null);
        if ($root === null) {
            return null;
        }

        return $this->managedWriter->find(PhotoService::ITEM_TYPE, [$this->sourceLocale() => $this->date($event)], $root);
    }

    private function date(Event $event): string
    {
        return $event->getStart()->format('Y-m-d');
    }

    /** @return array<string, string> */
    private function rootLabels(): array
    {
        $codes = $this->languageService->getFilteredEnabledCodes();
        $codes = $codes === [] ? [$this->sourceLocale()] : $codes;

        $labels = [];
        foreach ($codes as $code) {
            $labels[$code] = $this->translator->trans(self::ROOT_LABEL_KEY, [], null, $code);
        }

        return $labels;
    }

    private function sourceLocale(): string
    {
        return $this->languageService->getFilteredDefaultLocale();
    }
}
