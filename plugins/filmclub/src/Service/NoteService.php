<?php declare(strict_types=1);

namespace Plugin\Filmclub\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Filmclub\Entity\Film;
use Plugin\Filmclub\Entity\FilmNote;
use Plugin\Filmclub\Filter\FilmGroupFilterService;
use Plugin\Filmclub\Repository\FilmNoteRepository;

readonly class NoteService
{
    public function __construct(
        private EntityManagerInterface $em,
        private FilmNoteRepository $noteRepo,
        private FilmGroupFilterService $groupFilter,
    ) {}

    public function upsert(int $userId, Film $film, string $body, bool $revealToGroup): FilmNote
    {
        $note = $this->noteRepo->findUserNoteForFilm($userId, $film->getId());
        $wasRevealed = $note?->isRevealToGroup() ?? false;

        if ($note === null) {
            $note = new FilmNote();
            $note->setFilm($film);
            $note->setUserId($userId);
            $note->setCreatedAt(new DateTimeImmutable());
        } else {
            $note->setUpdatedAt(new DateTimeImmutable());
        }

        $note->setBody($body);
        $note->setRevealToGroup($revealToGroup);

        $this->em->persist($note);
        $this->em->flush();

        return $note;
    }

    public function getNoteForUser(int $userId, Film $film): ?FilmNote
    {
        return $this->noteRepo->findUserNoteForFilm($userId, $film->getId());
    }

    /** @return FilmNote[] */
    public function getRevealedForFilm(Film $film): array
    {
        return $this->noteRepo->findRevealedForFilm($film->getId(), $this->groupFilter->getAllowedNoteIds());
    }

    /** @return FilmNote[] */
    public function getForUser(int $userId): array
    {
        return $this->noteRepo->findBy(['userId' => $userId], ['createdAt' => 'DESC']);
    }
}
