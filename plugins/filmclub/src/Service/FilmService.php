<?php declare(strict_types=1);

namespace Plugin\Filmclub\Service;

use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Filmclub\Entity\ExternalSource;
use Plugin\Filmclub\Entity\Film;
use Plugin\Filmclub\Entity\FilmSuggestion;
use Plugin\Filmclub\Entity\SuggestionStatus;
use Plugin\Filmclub\Filter\FilmGroupFilterService;
use Plugin\Filmclub\Repository\FilmRepository;
use Plugin\Filmclub\Repository\FilmSuggestionRepository;
use RuntimeException;

readonly class FilmService
{
    public function __construct(
        private EntityManagerInterface $em,
        private FilmRepository $filmRepo,
        private FilmSuggestionRepository $suggestionRepo,
        private FilmGroupFilterService $groupFilter,
        private EntityActionDispatcher $dispatcher,
    ) {}

    public function createFromMetadata(FilmMetadata $metadata, int $userId, bool $isManager): Film
    {
        $existing = $this->filmRepo->findByExternalId($metadata->externalId, $metadata->source->value);
        if ($existing !== null) {
            throw new RuntimeException('filmclub_film.flash_already_exists');
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
        $film->setApproved($isManager);
        $film->setCreatedBy($userId);
        $film->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($film);
        $this->em->flush();

        $this->dispatcher->dispatch(EntityAction::CreateFilm, $film->getId());

        if (!$isManager) {
            $suggestion = $this->createSuggestionForFilm($film, $userId);
            $this->dispatcher->dispatch(EntityAction::CreateFilmSuggestion, $suggestion->getId());
        }

        return $film;
    }

    public function createManual(
        string $title,
        ?int $year,
        ?int $runtime,
        ?string $description,
        array $genres,
        int $userId,
        bool $isManager,
    ): Film {
        $film = new Film();
        $film->setTitle($title);
        $film->setYear($year);
        $film->setRuntime($runtime);
        $film->setDescription($description);
        $film->setGenres($genres);
        $film->setExternalSource(ExternalSource::Manual);
        $film->setApproved($isManager);
        $film->setCreatedBy($userId);
        $film->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($film);
        $this->em->flush();

        $this->dispatcher->dispatch(EntityAction::CreateFilm, $film->getId());

        if (!$isManager) {
            $suggestion = $this->createSuggestionForFilm($film, $userId);
            $this->dispatcher->dispatch(EntityAction::CreateFilmSuggestion, $suggestion->getId());
        }

        return $film;
    }

    public function approve(int $filmId): void
    {
        $film = $this->filmRepo->find($filmId);
        if ($film === null) {
            throw new RuntimeException('Film not found');
        }

        $film->setApproved(true);
        $this->em->persist($film);
        $this->em->flush();
    }

    public function reject(int $filmId): void
    {
        $film = $this->filmRepo->find($filmId);
        if ($film === null) {
            throw new RuntimeException('Film not found');
        }

        if ($film->isApproved()) {
            throw new RuntimeException('Cannot reject an already-approved film');
        }

        $suggestions = $this->suggestionRepo->findBy(['film' => $film]);
        foreach ($suggestions as $suggestion) {
            $this->em->remove($suggestion);
            $this->dispatcher->dispatch(EntityAction::DeleteFilmSuggestion, $suggestion->getId());
        }

        $this->em->remove($film);
        $this->em->flush();

        $this->dispatcher->dispatch(EntityAction::DeleteFilm, $filmId);
    }

    /** @return Film[] */
    public function getApprovedList(): array
    {
        return $this->filmRepo->findApproved($this->groupFilter->getAllowedFilmIds());
    }

    /** @return Film[] */
    public function getPendingList(): array
    {
        return $this->filmRepo->findPendingApproval($this->groupFilter->getAllowedFilmIds());
    }

    public function get(int $id): ?Film
    {
        return $this->filmRepo->find($id);
    }

    public function findByExternalId(string $externalId, ExternalSource $source): ?Film
    {
        return $this->filmRepo->findByExternalId($externalId, $source->value);
    }

    private function createSuggestionForFilm(Film $film, int $userId): FilmSuggestion
    {
        $suggestion = new FilmSuggestion();
        $suggestion->setFilm($film);
        $suggestion->setSuggestedBy($userId);
        $suggestion->setSuggestedAt(new DateTimeImmutable());
        $suggestion->setStatus(SuggestionStatus::Pending);
        $suggestion->setResubmitCount(0);

        $this->em->persist($suggestion);
        $this->em->flush();

        return $suggestion;
    }
}
