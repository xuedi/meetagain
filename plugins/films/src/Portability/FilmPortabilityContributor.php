<?php declare(strict_types=1);

namespace Plugin\Films\Portability;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Item\Portability\ItemImportContext;
use App\Item\Portability\ItemImportResult;
use App\Item\Portability\ItemPortabilityContributorInterface;
use App\Item\Portability\PortableImageWriterInterface;
use App\Service\Media\ImageLocationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Plugin\Films\Entity\ExternalSource;
use Plugin\Films\Entity\Film;
use Plugin\Films\Repository\FilmRepository;
use Plugin\Films\Service\FilmService;

/**
 * An external id from the same source identifies a film across instances; a manually entered film
 * has none, so title plus year is the fallback key.
 */
readonly class FilmPortabilityContributor implements ItemPortabilityContributorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private FilmRepository $filmRepo,
        private ImageLocationService $imageLocationService,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'films';
    }

    #[Override]
    public function getItemType(): string
    {
        return FilmService::ITEM_TYPE;
    }

    #[Override]
    public function exportItems(array $itemIds, PortableImageWriterInterface $images): array
    {
        $rows = [];

        foreach ($this->filmRepo->findBy(['id' => $itemIds]) as $film) {
            $rows[] = [
                'ref' => (int) $film->getId(),
                'title' => $film->getTitle(),
                'original_title' => $film->getOriginalTitle(),
                'year' => $film->getYear(),
                'runtime' => $film->getRuntime(),
                'external_id' => $film->getExternalId(),
                'external_source' => $film->getExternalSource()?->value,
                'description' => $film->getDescription(),
                'genres' => $film->getGenres(),
                'poster_image' => $film->getPosterImage() instanceof Image
                    ? $images->addImage($film->getPosterImage(), 'images/films/' . $film->getId() . '/poster')
                    : null,
            ];
        }

        return $rows;
    }

    #[Override]
    public function importItems(array $rows, ItemImportContext $context): ItemImportResult
    {
        $refToItemId = [];
        $created = 0;
        $matched = 0;
        $imageLocations = [];

        foreach ($rows as $row) {
            $ref = (int) ($row['ref'] ?? 0);
            $externalSource = ExternalSource::tryFrom((string) ($row['external_source'] ?? ''));
            $externalId = $this->nullableString($row['external_id'] ?? null);

            $existing = $this->findExisting($row, $externalSource, $externalId);
            if ($existing instanceof Film) {
                $refToItemId[$ref] = $existing;
                ++$matched;
                continue;
            }

            $film = new Film();
            $film->setTitle((string) ($row['title'] ?? ''));
            $film->setOriginalTitle($this->nullableString($row['original_title'] ?? null));
            $film->setYear($this->nullableInt($row['year'] ?? null));
            $film->setRuntime($this->nullableInt($row['runtime'] ?? null));
            $film->setExternalId($externalId);
            $film->setExternalSource($externalSource);
            $film->setDescription($this->nullableString($row['description'] ?? null));
            $film->setGenres(is_array($row['genres'] ?? null) ? $row['genres'] : []);
            $film->setCreatedBy((int) $context->getSystemUser()->getId());
            $film->setCreatedAt(new DateTimeImmutable());

            $poster = $context->importImage($this->nullableString($row['poster_image'] ?? null), ImageType::PluginFilmsPoster);
            if ($poster instanceof Image) {
                $film->setPosterImage($poster);
                $imageLocations[] = [$poster, $film];
            }

            $this->em->persist($film);
            $refToItemId[$ref] = $film;
            ++$created;
        }

        $this->em->flush();

        foreach ($imageLocations as [$image, $film]) {
            $this->imageLocationService->addLocation((int) $image->getId(), ImageType::PluginFilmsPoster, (int) $film->getId());
        }

        return new ItemImportResult(
            refToItemId: array_map(static fn(Film $film): int => (int) $film->getId(), $refToItemId),
            created: $created,
            matched: $matched,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function findExisting(array $row, ?ExternalSource $externalSource, ?string $externalId): ?Film
    {
        if ($externalSource !== null && $externalSource !== ExternalSource::Manual && $externalId !== null) {
            return $this->filmRepo->findOneBy(['externalSource' => $externalSource, 'externalId' => $externalId]);
        }

        $title = (string) ($row['title'] ?? '');
        if ($title === '') {
            return null;
        }

        return $this->filmRepo->findOneBy(['title' => $title, 'year' => $this->nullableInt($row['year'] ?? null)]);
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
