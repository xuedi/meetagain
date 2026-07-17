<?php declare(strict_types=1);

namespace Plugin\Books\Service\ImageTypes;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use App\Service\Media\ImageTypes\AbstractImageTypeDefinition;
use Doctrine\DBAL\Connection;
use Plugin\Books\Repository\BookRepository;

final class BookCoverImageTypeDefinition extends AbstractImageTypeDefinition
{
    public function __construct(
        ImageLocationRepository $repo,
        Connection $connection,
        private readonly BookRepository $bookRepository,
    ) {
        parent::__construct($repo, $connection);
    }

    public function getType(): ImageType
    {
        return ImageType::PluginBooksCover;
    }

    protected function sizes(): array
    {
        return [[400, 500], [350, 438], [200, 250]];
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'app_plugin_books_book_show', 'params' => ['id' => $locationId]];
    }

    public function discoverImageIds(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT cover_image_id AS image_id, id AS location_id FROM plg_books_book WHERE cover_image_id IS NOT NULL');

        return array_map(static fn(array $r) => [
            'imageId' => (int) $r['image_id'],
            'locationId' => (int) $r['location_id'],
        ], $rows);
    }

    public function locate(Image $image): ?array
    {
        $book = $this->bookRepository->findOneBy(['coverImage' => $image]);
        if ($book === null) {
            return null;
        }

        return [
            'label' => sprintf('Book cover: %s', $book->getTitle() ?? ''),
            'route' => 'app_plugin_books_book_show',
            'params' => ['id' => $book->getId()],
        ];
    }
}
