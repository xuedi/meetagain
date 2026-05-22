<?php declare(strict_types=1);

namespace Plugin\Filmclub\Service;

use App\Repository\EventRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Filmclub\Entity\Film;
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
        private EventRepository $eventRepo,
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

    public function countPastEventsInGroupSince(DateTimeImmutable $since): int
    {
        $allowedEventIds = $this->groupFilter->getAllowedEventIds();
        if ($allowedEventIds === []) {
            return 0;
        }

        $qb = $this->eventRepo
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.start >= :since')
            ->andWhere('e.start < :now')
            ->setParameter('since', $since)
            ->setParameter('now', new DateTimeImmutable());

        if ($allowedEventIds !== null) {
            $qb->andWhere('e.id IN (:ids)')->setParameter('ids', $allowedEventIds);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function onPollOutcome(Film $winner): void
    {
        $allowedIds = $this->groupFilter->getAllowedWishlistEntryIds();

        $this->wishlistRepo->incrementAllExceptWinner($winner->getId(), $allowedIds);
        $this->wishlistRepo->deleteByFilmInGroup($winner->getId(), $allowedIds);
    }
}
