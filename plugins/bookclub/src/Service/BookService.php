<?php declare(strict_types=1);

namespace Plugin\Bookclub\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Bookclub\Entity\Book;
use Plugin\Bookclub\Repository\BookRepository;
use RuntimeException;

readonly class BookService
{
    public function __construct(
        private EntityManagerInterface $em,
        private BookRepository $bookRepo,
        private IsbnLookupInterface $isbnLookup,
        private CoverImageService $coverImageService,
    ) {}

    public function createFromIsbn(string $isbn, int $userId, bool $isManager): ?Book
    {
        $normalizedIsbn = preg_replace('/[^0-9X]/', '', strtoupper($isbn)) ?? $isbn;

        $existing = $this->bookRepo->findByIsbn($normalizedIsbn);
        if ($existing !== null) {
            throw new RuntimeException('Book with this ISBN already exists');
        }

        $bookData = $this->isbnLookup->lookup($normalizedIsbn);
        if ($bookData === null) {
            return null;
        }

        $book = new Book();
        $book->setIsbn($bookData->isbn);
        $book->setTitle($bookData->title);
        $book->setAuthor($bookData->author);
        $book->setDescription($bookData->description);
        $book->setPageCount($bookData->pageCount);
        $book->setPublishedYear($bookData->publishedYear);
        $book->setCreatedBy($userId);
        $book->setCreatedAt(new DateTimeImmutable());
        $book->setApproved($isManager);

        if ($bookData->coverUrl !== null) {
            $image = $this->coverImageService->downloadAndSave($bookData->coverUrl, $userId);
            $book->setCoverImage($image);
        }

        $this->em->persist($book);
        $this->em->flush();

        return $book;
    }

    public function createManual(
        string $isbn,
        string $title,
        ?string $author,
        ?string $description,
        int $userId,
        bool $isManager
    ): Book {
        $normalizedIsbn = preg_replace('/[^0-9X]/', '', strtoupper($isbn)) ?? $isbn;

        $existing = $this->bookRepo->findByIsbn($normalizedIsbn);
        if ($existing !== null) {
            throw new RuntimeException('Book with this ISBN already exists');
        }

        $book = new Book();
        $book->setIsbn($normalizedIsbn);
        $book->setTitle($title);
        $book->setAuthor($author);
        $book->setDescription($description);
        $book->setCreatedBy($userId);
        $book->setCreatedAt(new DateTimeImmutable());
        $book->setApproved($isManager);

        $this->em->persist($book);
        $this->em->flush();

        return $book;
    }

    public function approve(int $bookId): void
    {
        $book = $this->bookRepo->find($bookId);
        if ($book === null) {
            throw new RuntimeException('Book not found');
        }

        $book->setApproved(true);
        $this->em->persist($book);
        $this->em->flush();
    }

    public function reject(int $bookId): void
    {
        $book = $this->bookRepo->find($bookId);
        if ($book === null) {
            throw new RuntimeException('Book not found');
        }

        if ($book->isApproved()) {
            throw new RuntimeException('Cannot reject approved book');
        }

        $this->em->remove($book);
        $this->em->flush();
    }

    /** @return Book[] */
    public function getApprovedList(): array
    {
        return $this->bookRepo->findBy(['approved' => true], ['title' => 'ASC']);
    }

    /** @return Book[] */
    public function getPendingList(): array
    {
        return $this->bookRepo->findBy(['approved' => false], ['createdAt' => 'DESC']);
    }

    /** @return Book[] */
    public function getAll(): array
    {
        return $this->bookRepo->findBy([], ['title' => 'ASC']);
    }

    public function get(int $id): ?Book
    {
        return $this->bookRepo->find($id);
    }

    public function getByIsbn(string $isbn): ?Book
    {
        $normalizedIsbn = preg_replace('/[^0-9X]/', '', strtoupper($isbn)) ?? $isbn;
        return $this->bookRepo->findByIsbn($normalizedIsbn);
    }
}
