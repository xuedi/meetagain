<?php declare(strict_types=1);

namespace Plugin\Bookclub\Tests\Unit\Service\ImageTypes;

use App\Entity\Image;
use App\Enum\ImageFitMode;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Plugin\Bookclub\Entity\Book;
use Plugin\Bookclub\Repository\BookRepository;
use Plugin\Bookclub\Service\ImageTypes\BookCoverImageTypeDefinition;

class BookCoverImageTypeDefinitionTest extends TestCase
{
    private function repo(): ImageLocationRepository
    {
        return $this->createStub(ImageLocationRepository::class);
    }

    public function testIdentitySizesFitModeAndEditLink(): void
    {
        $definition = new BookCoverImageTypeDefinition($this->repo(), $this->createStub(Connection::class), $this->createStub(BookRepository::class));

        static::assertSame(ImageType::PluginBookclubCover, $definition->getType());
        static::assertSame(ImageFitMode::Crop, $definition->fitMode());
        static::assertSame([[400, 500], [350, 438], [200, 250], [100, 100], [50, 50]], $definition->thumbnailSizes());
        static::assertSame(['route' => 'app_plugin_bookclub_book_show', 'params' => ['id' => 3]], $definition->getEditLink(3));
    }

    public function testDiscoverImageIds(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([['image_id' => '4', 'location_id' => '2']]);

        $definition = new BookCoverImageTypeDefinition($this->repo(), $conn, $this->createStub(BookRepository::class));

        static::assertSame([['imageId' => 4, 'locationId' => 2]], $definition->discoverImageIds());
    }

    public function testLocateResolvesBook(): void
    {
        $book = $this->createStub(Book::class);
        $book->method('getTitle')->willReturn('Dune');
        $book->method('getId')->willReturn(6);

        $bookRepo = $this->createStub(BookRepository::class);
        $bookRepo->method('findOneBy')->willReturn($book);

        $definition = new BookCoverImageTypeDefinition($this->repo(), $this->createStub(Connection::class), $bookRepo);

        static::assertSame(
            ['label' => 'Book cover: Dune', 'route' => 'app_plugin_bookclub_book_show', 'params' => ['id' => 6]],
            $definition->locate($this->createStub(Image::class)),
        );
    }

    public function testLocateReturnsNullWhenNoBook(): void
    {
        $bookRepo = $this->createStub(BookRepository::class);
        $bookRepo->method('findOneBy')->willReturn(null);

        $definition = new BookCoverImageTypeDefinition($this->repo(), $this->createStub(Connection::class), $bookRepo);

        static::assertNull($definition->locate($this->createStub(Image::class)));
    }
}
