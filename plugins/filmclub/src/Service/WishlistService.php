<?php declare(strict_types=1);

namespace Plugin\Filmclub\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Filmclub\Entity\Film;
use Plugin\Filmclub\Entity\FilmPoll;
use Plugin\Filmclub\Entity\FilmWishlistEntry;
use Plugin\Filmclub\Filter\FilmGroupFilterService;
use Plugin\Filmclub\Repository\FilmRepository;
use Plugin\Filmclub\Repository\FilmWishlistEntryRepository;

readonly class WishlistService
{
    public function __construct(
        private EntityManagerInterface $em,
        private FilmWishlistEntryRepository $wishlistRepo,
        private FilmRepository $filmRepo,
        private FilmGroupFilterService $groupFilter,
    ) {}

    public function add(Film $film, int $userId): FilmWishlistEntry
    {
        $existing = $this->wishlistRepo->findByUserAndFilm($userId, $film->getId());
        if ($existing !== null) {
            return $existing;
        }

        $entry = new FilmWishlistEntry();
        $entry->setFilm($film);
        $entry->setUserId($userId);
        $entry->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    public function remove(Film $film, int $userId): void
    {
        $entry = $this->wishlistRepo->findByUserAndFilm($userId, $film->getId());
        if ($entry === null) {
            return;
        }

        $this->em->remove($entry);
        $this->em->flush();
    }

    /** @return FilmWishlistEntry[] */
    public function listForUser(int $userId): array
    {
        return $this->wishlistRepo->findByUser($userId, $this->groupFilter->getAllowedWishlistEntryIds());
    }

    public function isWishlisted(Film $film, int $userId): bool
    {
        return $this->wishlistRepo->findByUserAndFilm($userId, $film->getId()) !== null;
    }

    public function getWanterCountForFilm(Film $film): int
    {
        return $this->wishlistRepo->countWantersForFilm($film->getId());
    }

    /**
     * Returns aggregated wishlist data sorted by distinct-wanter count (descending).
     * Each row: film entity, wanterCount (distinct users), totalPriority (sum of priorityCounter).
     *
     * @return array<array{film: Film, wanterCount: int, totalPriority: int}>
     */
    public function aggregateByFilm(): array
    {
        $rows = $this->wishlistRepo->aggregateByFilm($this->groupFilter->getAllowedWishlistEntryIds());
        if ($rows === []) {
            return [];
        }

        $filmIds = array_column($rows, 'film_id');
        $films = $this->filmRepo->findBy(['id' => $filmIds]);
        $filmMap = [];
        foreach ($films as $film) {
            $filmMap[$film->getId()] = $film;
        }

        $result = [];
        foreach ($rows as $row) {
            $film = $filmMap[$row['film_id']] ?? null;
            if ($film === null) {
                continue;
            }
            $result[] = [
                'film' => $film,
                'wanterCount' => (int) $row['wanter_count'],
                'totalPriority' => (int) $row['total_priority'],
            ];
        }

        return $result;
    }

    /**
     * Returns all wishlist entries grouped by userId, sorted by priorityCounter descending within each group.
     *
     * @return array<int, FilmWishlistEntry[]>
     */
    public function groupByMember(): array
    {
        $entries = $this->wishlistRepo->findAllForGroupView($this->groupFilter->getAllowedWishlistEntryIds());
        $grouped = [];
        foreach ($entries as $entry) {
            $grouped[$entry->getUserId()][] = $entry;
        }

        return $grouped;
    }

    /**
     * Increments priorityCounter for every wishlist entry whose film was in the poll
     * but was NOT the winning film. Called at poll outcome commitment (Phase 7).
     */
    public function incrementForLosers(FilmPoll $poll, Film $winner): void
    {
        foreach ($poll->getSuggestions() as $suggestion) {
            $film = $suggestion->getFilm();
            if ($film === null || $film->getId() === $winner->getId()) {
                continue;
            }

            $entries = $this->wishlistRepo->findByFilmForIncrement($film->getId());
            foreach ($entries as $entry) {
                $entry->setPriorityCounter($entry->getPriorityCounter() + 1);
                $this->em->persist($entry);
            }
        }

        $this->em->flush();
    }
}
