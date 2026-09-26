<?php declare(strict_types=1);

namespace Plugin\Karaoke\Portability;

use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Item\ContributorInterface;
use App\Portability\Item\ImportResult;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Plugin\Karaoke\Entity\LyricLine;
use Plugin\Karaoke\Entity\Song;
use Plugin\Karaoke\Enum\MediaProvider;
use Plugin\Karaoke\Repository\SongRepository;
use Plugin\Karaoke\Service\SongService;
use Plugin\Karaoke\ValueObject\MediaLink;

readonly class SongContributor implements ContributorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private SongRepository $songRepo,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'karaoke';
    }

    #[Override]
    public function getItemType(): string
    {
        return SongService::ITEM_TYPE;
    }

    #[Override]
    public function allItemIds(): array
    {
        return array_map(
            intval(...),
            $this->songRepo
                ->createQueryBuilder('s')
                ->select('s.id')
                ->orderBy('s.id')
                ->getQuery()
                ->getSingleColumnResult(),
        );
    }

    #[Override]
    public function exportItems(array $itemIds, ImageWriterInterface $images): array
    {
        $rows = [];
        foreach ($this->songRepo->findBy(['id' => $itemIds], ['id' => 'ASC']) as $song) {
            $lines = [];
            foreach ($song->getLines() as $line) {
                $translations = [];
                foreach ($line->getTranslations() as $translation) {
                    $translations[(string) $translation->getLanguage()] = $translation->getText();
                }
                ksort($translations);
                $lines[] = [
                    'start_ms' => $line->getStartMs(),
                    'text' => $line->getText(),
                    'translations' => $translations,
                ];
            }

            $rows[] = [
                'ref' => (int) $song->getId(),
                'title' => $song->getTitle(),
                'artist' => $song->getArtist(),
                'language' => $song->getLanguage(),
                'media' => ['provider' => $song->getMediaProvider()?->value, 'id' => $song->getMediaId()],
                'offset_ms' => $song->getOffsetMs(),
                'lines' => $lines,
            ];
        }

        return $rows;
    }

    #[Override]
    public function importItems(array $rows, ImportContext $context): ImportResult
    {
        $refToSong = [];
        foreach ($rows as $row) {
            $link = $this->mediaLink($row['media'] ?? null);
            if ($link === null) {
                continue;
            }

            $song = new Song();
            $song->setTitle((string) ($row['title'] ?? ''));
            $song->setArtist(($row['artist'] ?? '') === '' ? null : (string) $row['artist']);
            $song->setLanguage((string) ($row['language'] ?? ''));
            $song->setMediaLink($link);
            $song->setOffsetMs((int) ($row['offset_ms'] ?? 0));
            $song->setCreatedBy((int) $context->getSystemUser()->getId());
            $song->setCreatedAt(new DateTimeImmutable());

            foreach (is_array($row['lines'] ?? null) ? array_values($row['lines']) : [] as $position => $lineData) {
                $line = new LyricLine();
                $line->setPosition($position);
                $line->setStartMs(isset($lineData['start_ms']) ? (int) $lineData['start_ms'] : null);
                $line->setText((string) ($lineData['text'] ?? ''));
                foreach (is_array($lineData['translations'] ?? null) ? $lineData['translations'] : [] as $language => $text) {
                    $line->setTranslation((string) $language, (string) $text);
                }
                $song->addLine($line);
            }

            $this->em->persist($song);
            $refToSong[(int) ($row['ref'] ?? 0)] = $song;
        }

        $this->em->flush();

        return new ImportResult(refToItemId: array_map(static fn(Song $song): int => (int) $song->getId(), $refToSong), created: count($refToSong), matched: 0);
    }

    private function mediaLink(mixed $media): ?MediaLink
    {
        if (!is_array($media)) {
            return null;
        }

        $provider = MediaProvider::tryFrom((string) ($media['provider'] ?? ''));
        $id = (string) ($media['id'] ?? '');
        if ($provider === null || !$provider->isValidId($id)) {
            return null;
        }

        return new MediaLink($provider, $id);
    }
}
