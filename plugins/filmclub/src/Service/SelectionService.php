<?php declare(strict_types=1);

namespace Plugin\Filmclub\Service;

use App\Entity\Event;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Filmclub\Entity\Film;
use Plugin\Filmclub\Entity\FilmSelection;
use Plugin\Filmclub\Filter\FilmGroupFilterService;
use Plugin\Filmclub\Repository\FilmSelectionRepository;
use RuntimeException;

readonly class SelectionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private FilmSelectionRepository $selectionRepo,
        private FilmGroupFilterService $groupFilter,
        private WishlistService $wishlistService,
    ) {}

    public function selectForEvent(int $eventId, Film $film, int $selectedBy): FilmSelection
    {
        $existing = $this->selectionRepo->findByEvent($eventId);
        if ($existing !== null) {
            throw new RuntimeException('filmclub_manage.flash_already_selected');
        }

        $selection = new FilmSelection();
        $selection->setFilm($film);
        $selection->setEventId($eventId);
        $selection->setSelectedBy($selectedBy);
        $selection->setSelectedAt(new DateTimeImmutable());

        $this->em->persist($selection);
        $this->em->flush();

        return $selection;
    }

    public function getForEvent(int $eventId): ?FilmSelection
    {
        return $this->selectionRepo->findByEvent($eventId);
    }

    /** @return FilmSelection[] */
    public function getSelectionsForFilm(Film $film): array
    {
        return $this->selectionRepo->findByFilm($film->getId());
    }

    /** @return FilmSelection[] */
    public function getHistory(): array
    {
        return $this->selectionRepo->findHistoryFilteredByEvents($this->groupFilter->getAllowedEventIds());
    }

    public function chooseDirectly(Event $event, Film $film, int $userId): FilmSelection
    {
        $existing = $this->selectionRepo->findByEvent($event->getId());
        if ($existing !== null) {
            throw new RuntimeException('filmclub_manage.flash_already_selected');
        }

        $selection = new FilmSelection();
        $selection->setFilm($film);
        $selection->setEventId($event->getId());
        $selection->setSelectedBy($userId);
        $selection->setSelectedAt(new DateTimeImmutable());

        $this->em->persist($selection);
        $this->em->flush();

        $this->wishlistService->onPollOutcome($film);

        return $selection;
    }
}
