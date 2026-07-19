<?php declare(strict_types=1);

namespace Plugin\Films\Service;

use App\Enum\ImageType;
use App\Enum\ItemAction;
use App\Item\ItemActionDispatcher;
use App\Item\ItemFilterService;
use App\Service\Media\ImageLocationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Films\Entity\ExternalSource;
use Plugin\Films\Entity\Film;
use Plugin\Films\Repository\FilmRepository;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

readonly class FilmService
{
    public const string ITEM_TYPE = 'film';

    public function __construct(
        private EntityManagerInterface $em,
        private FilmRepository $filmRepo,
        private ItemFilterService $itemFilter,
        private ItemActionDispatcher $dispatcher,
        private PosterImageService $posterImageService,
        private ImageLocationService $imageLocationService,
    ) {}

    public function createFromMetadata(FilmMetadata $metadata, int $userId): Film
    {
        $existing = $this->filmRepo->findByExternalId($metadata->externalId, $metadata->source->value);
        if ($existing !== null) {
            throw new RuntimeException('films_film.flash_already_exists');
        }

        $film = new Film();
        $film->setTitle($metadata->title);
        $film->setOriginalTitle($metadata->originalTitle);
        $film->setYear($metadata->year);
        $film->setRuntime($metadata->runtime);
        $film->setDescription($metadata->description);
        $film->setGenres($metadata->genres);
        $film->setExternalId($metadata->externalId);
        $film->setExternalSource($metadata->source);
        $film->setCreatedBy($userId);
        $film->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($film);
        $this->em->flush();

        if ($metadata->posterUrl !== null) {
            $poster = $this->posterImageService->downloadAndSave($metadata->posterUrl, $userId);
            if ($poster !== null) {
                $film->setPosterImage($poster);
                $this->em->persist($film);
                $this->em->flush();
                $this->imageLocationService->addLocation($poster->getId(), ImageType::PluginFilmsPoster, $film->getId());
            }
        }

        $this->dispatcher->dispatch(ItemAction::Created, self::ITEM_TYPE, (int) $film->getId());

        return $film;
    }

    public function createManual(string $title, ?int $year, ?int $runtime, ?string $description, array $genres, int $userId): Film
    {
        $film = new Film();
        $film->setTitle($title);
        $film->setYear($year);
        $film->setRuntime($runtime);
        $film->setDescription($description);
        $film->setGenres($genres);
        $film->setExternalSource(ExternalSource::Manual);
        $film->setCreatedBy($userId);
        $film->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($film);
        $this->em->flush();

        $this->dispatcher->dispatch(ItemAction::Created, self::ITEM_TYPE, (int) $film->getId());

        return $film;
    }

    public function update(Film $film, ?string $genresCsv, ?UploadedFile $posterFile, int $userId): Film
    {
        $film->setGenres($this->parseGenres($genresCsv));

        $previousPosterId = null;
        $newPoster = null;
        if ($posterFile !== null) {
            $previousPoster = $film->getPosterImage();
            $previousPosterId = $previousPoster?->getId();
            $newPoster = $this->posterImageService->uploadFromFile($posterFile, $userId);
            if ($newPoster === null) {
                throw new RuntimeException('films_film.flash_invalid_image');
            }
            $film->setPosterImage($newPoster);
        }

        $this->em->persist($film);
        $this->em->flush();

        if ($newPoster !== null) {
            if ($previousPosterId !== null && $previousPosterId !== $newPoster->getId()) {
                $this->imageLocationService->removeLocation($previousPosterId, ImageType::PluginFilmsPoster, $film->getId());
            }

            $this->imageLocationService->addLocation($newPoster->getId(), ImageType::PluginFilmsPoster, $film->getId());
        }

        $this->dispatcher->dispatch(ItemAction::Updated, self::ITEM_TYPE, (int) $film->getId());

        return $film;
    }

    public function delete(Film $film): void
    {
        $filmId = (int) $film->getId();
        $poster = $film->getPosterImage();
        if ($poster !== null) {
            $this->imageLocationService->removeLocation((int) $poster->getId(), ImageType::PluginFilmsPoster, $filmId);
        }

        $this->em->remove($film);
        $this->em->flush();

        $this->dispatcher->dispatch(ItemAction::Deleted, self::ITEM_TYPE, $filmId);
    }

    /** @return string[] */
    private function parseGenres(?string $csv): array
    {
        if ($csv === null || trim($csv) === '') {
            return [];
        }

        $parts = array_map(static fn(string $g) => strtolower(trim($g)), explode(',', $csv));
        $parts = array_filter($parts, static fn(string $g) => $g !== '');

        return array_values(array_unique($parts));
    }

    /** @return Film[] */
    public function getList(): array
    {
        return $this->filmRepo->findAll($this->itemFilter->getAllowedItemIds(self::ITEM_TYPE));
    }

    public function get(int $id): ?Film
    {
        return $this->filmRepo->find($id);
    }

    public function findByExternalId(string $externalId, ExternalSource $source): ?Film
    {
        return $this->filmRepo->findByExternalId($externalId, $source->value);
    }
}
