<?php declare(strict_types=1);

namespace Plugin\Filmclub\Service;

use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Filmclub\Entity\Film;
use Plugin\Filmclub\Entity\FilmSuggestion;
use Plugin\Filmclub\Entity\SuggestionStatus;
use Plugin\Filmclub\Filter\FilmGroupFilterService;
use Plugin\Filmclub\Repository\FilmSuggestionRepository;
use RuntimeException;

readonly class SuggestionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private FilmSuggestionRepository $suggestionRepo,
        private FilmGroupFilterService $groupFilter,
        private EntityActionDispatcher $dispatcher,
    ) {}

    public function suggest(Film $film, int $userId): FilmSuggestion
    {
        if (!$film->isApproved()) {
            throw new RuntimeException('filmclub_suggestion.flash_not_approved');
        }

        $existing = $this->suggestionRepo->findUserPending($userId, $this->groupFilter->getAllowedSuggestionIds());
        foreach ($existing as $s) {
            if ($s->getFilm()->getId() === $film->getId()) {
                throw new RuntimeException('filmclub_suggestion.flash_duplicate');
            }
        }

        $suggestion = new FilmSuggestion();
        $suggestion->setFilm($film);
        $suggestion->setSuggestedBy($userId);
        $suggestion->setSuggestedAt(new DateTimeImmutable());
        $suggestion->setStatus(SuggestionStatus::Pending);
        $suggestion->setResubmitCount(0);

        $this->em->persist($suggestion);
        $this->em->flush();

        $this->dispatcher->dispatch(EntityAction::CreateFilmSuggestion, $suggestion->getId());

        return $suggestion;
    }

    public function withdraw(int $suggestionId, int $userId): void
    {
        $suggestion = $this->suggestionRepo->find($suggestionId);
        if ($suggestion === null) {
            throw new RuntimeException('Suggestion not found');
        }

        if ($suggestion->getSuggestedBy() !== $userId) {
            throw new RuntimeException('filmclub_suggestion.flash_not_yours');
        }

        if ($suggestion->getStatus() !== SuggestionStatus::Pending) {
            throw new RuntimeException('filmclub_suggestion.flash_not_withdrawable');
        }

        $suggestion->setStatus(SuggestionStatus::Withdrawn);
        $this->em->persist($suggestion);
        $this->em->flush();

        $this->dispatcher->dispatch(EntityAction::DeleteFilmSuggestion, $suggestionId);
    }

    /** @return FilmSuggestion[] */
    public function getUserPendingSuggestions(int $userId): array
    {
        return $this->suggestionRepo->findUserPending($userId, $this->groupFilter->getAllowedSuggestionIds());
    }

    /** @return FilmSuggestion[] */
    public function getPendingSuggestions(): array
    {
        return $this->suggestionRepo->findAllPending($this->groupFilter->getAllowedSuggestionIds());
    }

    public function get(int $id): ?FilmSuggestion
    {
        return $this->suggestionRepo->find($id);
    }
}
