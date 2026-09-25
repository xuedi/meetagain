<?php declare(strict_types=1);

namespace Plugin\Karaoke\Service;

use App\Enum\ItemAction;
use App\Item\ActionDispatcher;
use App\Item\AdminFilterService;
use App\Item\FilterService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Karaoke\Entity\LyricLine;
use Plugin\Karaoke\Entity\Song;
use Plugin\Karaoke\Repository\SongRepository;
use Plugin\Karaoke\ValueObject\MediaLink;

readonly class SongService
{
    public const string ITEM_TYPE = 'song';
    public const int MAX_OFFSET_MS = 60_000;

    public function __construct(
        private EntityManagerInterface $em,
        private SongRepository $songRepo,
        private FilterService $itemFilter,
        private AdminFilterService $adminItemFilter,
        private ActionDispatcher $dispatcher,
        private LyricsText $lyricsText,
    ) {}

    public function create(string $title, ?string $artist, string $language, MediaLink $link, string $lyrics, string $translationLanguage, int $userId): Song
    {
        $song = new Song();
        $song->setTitle($title);
        $song->setArtist($artist);
        $song->setLanguage($language);
        $song->setMediaLink($link);
        $song->setCreatedBy($userId);
        $song->setCreatedAt(new DateTimeImmutable());
        $this->applyLyrics($song, $this->lyricsText->parse($lyrics), $translationLanguage);

        $this->em->persist($song);
        $this->em->flush();

        $this->dispatcher->dispatch(ItemAction::Created, self::ITEM_TYPE, (int) $song->getId());

        return $song;
    }

    public function update(Song $song, string $title, ?string $artist, string $language, MediaLink $link, string $lyrics, string $translationLanguage): void
    {
        $song->setTitle($title);
        $song->setArtist($artist);
        $song->setLanguage($language);
        $song->setMediaLink($link);
        $this->applyLyrics($song, $this->lyricsText->parse($lyrics), $translationLanguage);

        $this->em->flush();

        $this->dispatcher->dispatch(ItemAction::Updated, self::ITEM_TYPE, (int) $song->getId());
    }

    /** @param list<?int> $startTimes */
    public function saveTiming(Song $song, array $startTimes, int $offsetMs): void
    {
        foreach ($song->getLines()->getValues() as $index => $line) {
            $line->setStartMs(array_key_exists($index, $startTimes) ? $startTimes[$index] : $line->getStartMs());
        }
        $song->setOffsetMs(max(-self::MAX_OFFSET_MS, min(self::MAX_OFFSET_MS, $offsetMs)));

        $this->em->flush();
    }

    public function setOffset(Song $song, int $offsetMs): void
    {
        $song->setOffsetMs(max(-self::MAX_OFFSET_MS, min(self::MAX_OFFSET_MS, $offsetMs)));

        $this->em->flush();
    }

    public function nudgeOffset(Song $song, int $deltaMs): void
    {
        $song->setOffsetMs(max(-self::MAX_OFFSET_MS, min(self::MAX_OFFSET_MS, $song->getOffsetMs() + $deltaMs)));

        $this->em->flush();
    }

    public function delete(Song $song): void
    {
        $songId = (int) $song->getId();

        $this->em->remove($song);
        $this->em->flush();

        $this->dispatcher->dispatch(ItemAction::Deleted, self::ITEM_TYPE, $songId);
    }

    public function lyricsFor(Song $song, string $translationLanguage): string
    {
        $lines = [];
        foreach ($song->getLines() as $line) {
            $lines[] = [
                'startMs' => $line->getStartMs(),
                'text' => $line->getText(),
                'translation' => $line->findTranslation($translationLanguage)?->getText(),
            ];
        }

        return $this->lyricsText->format($lines);
    }

    /** @return list<Song> */
    public function getList(): array
    {
        return $this->songRepo->findAllowed($this->itemFilter->getAllowedItemIds(self::ITEM_TYPE));
    }

    public function get(int $id): ?Song
    {
        return $this->songRepo->findOneAllowed($id, $this->itemFilter->getAllowedItemIds(self::ITEM_TYPE));
    }

    public function getManaged(int $id): ?Song
    {
        return $this->songRepo->findOneAllowed($id, $this->adminItemFilter->getAllowedItemIds(self::ITEM_TYPE));
    }

    /**
     * @param list<array{startMs: ?int, text: string, translation: ?string}> $parsed
     */
    private function applyLyrics(Song $song, array $parsed, string $translationLanguage): void
    {
        $existing = $song->getLines()->getValues();

        foreach ($parsed as $position => $row) {
            $line = $existing[$position] ?? new LyricLine();
            $line->setPosition($position);
            $line->setText($row['text']);
            $line->setStartMs($row['startMs']);
            $line->setTranslation($translationLanguage, $row['translation']);
            $song->addLine($line);
        }

        foreach (array_slice($existing, count($parsed)) as $surplus) {
            $song->removeLine($surplus);
        }
    }
}
