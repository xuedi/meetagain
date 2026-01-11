<?php declare(strict_types=1);

namespace Plugin\Bookclub\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Bookclub\Entity\Book;
use Plugin\Bookclub\Entity\BookNote;
use Plugin\Bookclub\Repository\BookNoteRepository;

readonly class NoteService
{
    public function __construct(
        private EntityManagerInterface $em,
        private BookNoteRepository $noteRepo,
    ) {}

    public function saveNote(Book $book, int $userId, string $content): BookNote
    {
        $note = $this->noteRepo->findUserNote($book, $userId);

        if ($note === null) {
            $note = new BookNote();
            $note->setBook($book);
            $note->setUserId($userId);
            $note->setCreatedAt(new DateTimeImmutable());
        } else {
            $note->setUpdatedAt(new DateTimeImmutable());
        }

        $note->setContent($content);

        $this->em->persist($note);
        $this->em->flush();

        return $note;
    }

    public function getNote(Book $book, int $userId): ?BookNote
    {
        return $this->noteRepo->findUserNote($book, $userId);
    }

    /** @return BookNote[] */
    public function getUserNotes(int $userId): array
    {
        return $this->noteRepo->findUserNotes($userId);
    }

    public function delete(int $noteId, int $userId): void
    {
        $note = $this->noteRepo->find($noteId);
        if ($note !== null && $note->getUserId() === $userId) {
            $this->em->remove($note);
            $this->em->flush();
        }
    }
}
