<?php declare(strict_types=1);

namespace Plugin\Books\Service;

use App\Enum\ImageType;
use App\Enum\ItemAction;
use App\Item\ItemActionDispatcher;
use App\Item\ItemFilterService;
use App\Service\Media\ImageLocationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Books\Entity\Book;
use Plugin\Books\Repository\BookRepository;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

readonly class BookService
{
    public const string ITEM_TYPE = 'book';

    public function __construct(
        private EntityManagerInterface $em,
        private BookRepository $bookRepo,
        private IsbnLookupInterface $isbnLookup,
        private CoverImageService $coverImageService,
        private ItemFilterService $itemFilter,
        private ItemActionDispatcher $dispatcher,
        private ImageLocationService $imageLocationService,
    ) {}

    public function createFromIsbn(string $isbn, int $userId): ?Book
    {
        $normalizedIsbn = $this->normalizeIsbn($isbn);
        if ($this->bookRepo->findByIsbn($normalizedIsbn) !== null) {
            throw new RuntimeException('books_book.flash_already_exists');
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

        if ($bookData->coverUrl !== null) {
            $book->setCoverImage($this->coverImageService->downloadAndSave($bookData->coverUrl, $userId));
        }

        $this->em->persist($book);
        $this->em->flush();

        $cover = $book->getCoverImage();
        if ($cover !== null) {
            $this->imageLocationService->addLocation((int) $cover->getId(), ImageType::PluginBooksCover, (int) $book->getId());
        }

        $this->dispatcher->dispatch(ItemAction::Created, self::ITEM_TYPE, (int) $book->getId());

        return $book;
    }

    public function createManual(string $isbn, string $title, ?string $author, ?string $description, ?int $pageCount, ?int $publishedYear, int $userId): Book
    {
        $normalizedIsbn = $this->normalizeIsbn($isbn);
        if ($this->bookRepo->findByIsbn($normalizedIsbn) !== null) {
            throw new RuntimeException('books_book.flash_already_exists');
        }

        $book = new Book();
        $book->setIsbn($normalizedIsbn);
        $book->setTitle($title);
        $book->setAuthor($author);
        $book->setDescription($description);
        $book->setPageCount($pageCount);
        $book->setPublishedYear($publishedYear);
        $book->setCreatedBy($userId);
        $book->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($book);
        $this->em->flush();

        $this->dispatcher->dispatch(ItemAction::Created, self::ITEM_TYPE, (int) $book->getId());

        return $book;
    }

    public function update(Book $book, ?UploadedFile $coverFile, int $userId): Book
    {
        $previousCoverId = null;
        $newCover = null;
        if ($coverFile !== null) {
            $previousCoverId = $book->getCoverImage()?->getId();
            $newCover = $this->coverImageService->uploadFromFile($coverFile, $userId);
            if ($newCover === null) {
                throw new RuntimeException('books_book.flash_invalid_image');
            }
            $book->setCoverImage($newCover);
        }

        $this->em->persist($book);
        $this->em->flush();

        if ($newCover !== null) {
            if ($previousCoverId !== null && $previousCoverId !== $newCover->getId()) {
                $this->imageLocationService->removeLocation($previousCoverId, ImageType::PluginBooksCover, (int) $book->getId());
            }

            $this->imageLocationService->addLocation((int) $newCover->getId(), ImageType::PluginBooksCover, (int) $book->getId());
        }

        $this->dispatcher->dispatch(ItemAction::Updated, self::ITEM_TYPE, (int) $book->getId());

        return $book;
    }

    public function delete(Book $book): void
    {
        $bookId = (int) $book->getId();
        $cover = $book->getCoverImage();
        if ($cover !== null) {
            $this->imageLocationService->removeLocation((int) $cover->getId(), ImageType::PluginBooksCover, $bookId);
        }

        $this->em->remove($book);
        $this->em->flush();

        $this->dispatcher->dispatch(ItemAction::Deleted, self::ITEM_TYPE, $bookId);
    }

    /** @return Book[] */
    public function getList(): array
    {
        return $this->bookRepo->findAll($this->itemFilter->getAllowedItemIds(self::ITEM_TYPE));
    }

    public function get(int $id): ?Book
    {
        return $this->bookRepo->find($id);
    }

    public function getByIsbn(string $isbn): ?Book
    {
        return $this->bookRepo->findByIsbn($this->normalizeIsbn($isbn));
    }

    private function normalizeIsbn(string $isbn): string
    {
        return preg_replace('/[^0-9X]/', '', strtoupper($isbn)) ?? $isbn;
    }
}
