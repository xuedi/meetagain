<?php declare(strict_types=1);

namespace Plugin\Books\Portability;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Item\Portability\ItemImportContext;
use App\Item\Portability\ItemImportResult;
use App\Item\Portability\ItemPortabilityContributorInterface;
use App\Item\Portability\PortableImageWriterInterface;
use App\Service\Media\ImageLocationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Plugin\Books\Entity\Book;
use Plugin\Books\Repository\BookRepository;
use Plugin\Books\Service\BookService;

/**
 * Deduplication on ISBN is mandatory rather than a nicety: Book::$isbn is a unique column, so a
 * colliding row must resolve to the book already present instead of being inserted.
 */
readonly class BookPortabilityContributor implements ItemPortabilityContributorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private BookRepository $bookRepo,
        private ImageLocationService $imageLocationService,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'books';
    }

    #[Override]
    public function getItemType(): string
    {
        return BookService::ITEM_TYPE;
    }

    #[Override]
    public function exportItems(array $itemIds, PortableImageWriterInterface $images): array
    {
        $rows = [];

        foreach ($this->bookRepo->findBy(['id' => $itemIds]) as $book) {
            $rows[] = [
                'ref' => (int) $book->getId(),
                'isbn' => $book->getIsbn(),
                'title' => $book->getTitle(),
                'author' => $book->getAuthor(),
                'description' => $book->getDescription(),
                'page_count' => $book->getPageCount(),
                'published_year' => $book->getPublishedYear(),
                'cover_image' => $book->getCoverImage() instanceof Image
                    ? $images->addImage($book->getCoverImage(), 'images/books/' . $book->getId() . '/cover')
                    : null,
            ];
        }

        return $rows;
    }

    #[Override]
    public function importItems(array $rows, ItemImportContext $context): ItemImportResult
    {
        $refToItemId = [];
        $created = 0;
        $matched = 0;
        $imageLocations = [];

        foreach ($rows as $row) {
            $ref = (int) ($row['ref'] ?? 0);
            $isbn = (string) ($row['isbn'] ?? '');

            $existing = $isbn !== '' ? $this->bookRepo->findOneBy(['isbn' => $isbn]) : null;
            if ($existing instanceof Book) {
                $refToItemId[$ref] = $existing;
                ++$matched;
                continue;
            }

            $book = new Book();
            $book->setIsbn($isbn);
            $book->setTitle((string) ($row['title'] ?? ''));
            $book->setAuthor($this->nullableString($row['author'] ?? null));
            $book->setDescription($this->nullableString($row['description'] ?? null));
            $book->setPageCount($this->nullableInt($row['page_count'] ?? null));
            $book->setPublishedYear($this->nullableInt($row['published_year'] ?? null));
            $book->setCreatedBy((int) $context->getSystemUser()->getId());
            $book->setCreatedAt(new DateTimeImmutable());

            $cover = $context->importImage($this->nullableString($row['cover_image'] ?? null), ImageType::PluginBooksCover);
            if ($cover instanceof Image) {
                $book->setCoverImage($cover);
                $imageLocations[] = [$cover, $book];
            }

            $this->em->persist($book);
            $refToItemId[$ref] = $book;
            ++$created;
        }

        $this->em->flush();

        foreach ($imageLocations as [$image, $book]) {
            $this->imageLocationService->addLocation((int) $image->getId(), ImageType::PluginBooksCover, (int) $book->getId());
        }

        return new ItemImportResult(
            refToItemId: array_map(static fn(Book $book): int => (int) $book->getId(), $refToItemId),
            created: $created,
            matched: $matched,
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
