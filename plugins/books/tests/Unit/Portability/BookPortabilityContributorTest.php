<?php declare(strict_types=1);

namespace Plugin\Books\Tests\Unit\Portability;

use App\Entity\Image;
use App\Entity\User;
use App\Item\Portability\ItemImportContext;
use App\Item\Portability\PortableImageWriterInterface;
use App\Service\Media\ImageLocationService;
use App\Service\System\PortableImageImporter;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Books\Entity\Book;
use Plugin\Books\Portability\BookPortabilityContributor;
use Plugin\Books\Repository\BookRepository;
use ReflectionProperty;

class BookPortabilityContributorTest extends TestCase
{
    public function testExportCarriesTheBookFieldsAndCoverPath(): void
    {
        // Arrange
        $book = $this->book(4, '9781234567897', 'Dune');
        $book->setCoverImage(new Image());

        $repo = $this->createStub(BookRepository::class);
        $repo->method('findBy')->willReturn([$book]);

        $images = $this->createStub(PortableImageWriterInterface::class);
        $images->method('addImage')->willReturnCallback(static fn(Image $image, string $hint): string => $hint . '.jpg');

        $contributor = $this->contributor($this->createStub(EntityManagerInterface::class), $repo);

        // Act
        $rows = $contributor->exportItems([4], $images);

        // Assert
        self::assertSame(4, $rows[0]['ref']);
        self::assertSame('9781234567897', $rows[0]['isbn']);
        self::assertSame('Dune', $rows[0]['title']);
        self::assertSame('images/books/4/cover.jpg', $rows[0]['cover_image']);
    }

    public function testCollidingIsbnResolvesToTheExistingBook(): void
    {
        // Arrange - the ISBN column is unique, so a second insert would throw
        $existing = $this->book(77, '9781234567897', 'Dune');
        $repo = $this->createStub(BookRepository::class);
        $repo->method('findOneBy')->willReturn($existing);

        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $contributor = $this->contributor($em, $repo);

        // Act
        $result = $contributor->importItems([['ref' => 4, 'isbn' => '9781234567897', 'title' => 'Dune']], $this->context());

        // Assert
        self::assertSame([4 => 77], $result->refToItemId);
        self::assertSame(0, $result->created);
        self::assertSame(1, $result->matched);
        self::assertSame([], $persisted);
    }

    public function testUnknownIsbnCreatesTheBook(): void
    {
        // Arrange
        $repo = $this->createStub(BookRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity): void {
            if ($entity instanceof Book) {
                new ReflectionProperty(Book::class, 'id')->setValue($entity, 55);
            }
        });

        $contributor = $this->contributor($em, $repo);
        $rows = [['ref' => 4, 'isbn' => '9789999999999', 'title' => 'Dune', 'author' => 'Herbert', 'page_count' => 412]];

        // Act
        $result = $contributor->importItems($rows, $this->context());

        // Assert
        self::assertSame([4 => 55], $result->refToItemId);
        self::assertSame(1, $result->created);
        self::assertSame(0, $result->matched);
    }

    private function book(int $id, string $isbn, string $title): Book
    {
        $book = new Book();
        new ReflectionProperty(Book::class, 'id')->setValue($book, $id);
        $book->setIsbn($isbn);
        $book->setTitle($title);
        $book->setCreatedBy(1);
        $book->setCreatedAt(new DateTimeImmutable());

        return $book;
    }

    private function context(): ItemImportContext
    {
        return new ItemImportContext($this->createStub(PortableImageImporter::class), '/tmp', new User());
    }

    private function contributor(EntityManagerInterface $em, BookRepository $repo): BookPortabilityContributor
    {
        return new BookPortabilityContributor($em, $repo, $this->createStub(ImageLocationService::class));
    }
}
