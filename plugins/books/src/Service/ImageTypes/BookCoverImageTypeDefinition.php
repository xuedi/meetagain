<?php declare(strict_types=1);

namespace Plugin\Books\Service\ImageTypes;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Item\FilterService;
use App\Repository\ImageLocationRepository;
use App\Service\Media\ImageTypes\AbstractImageTypeDefinition;
use Doctrine\DBAL\Connection;
use Plugin\Books\Repository\BookRepository;
use Plugin\Books\Service\BookService;

final readonly class BookCoverImageTypeDefinition extends AbstractImageTypeDefinition
{
    public function __construct(
        ImageLocationRepository $repo,
        Connection $connection,
        private BookRepository $bookRepository,
        private FilterService $itemFilter,
    ) {
        parent::__construct($repo, $connection);
    }

    public function getType(): ImageType
    {
        return ImageType::PluginBooksCover;
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'app_plugin_books_book_show', 'params' => ['id' => $locationId]];
    }

    public function discoverImageIds(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT cover_image_id AS image_id, id AS location_id FROM plg_books_book WHERE cover_image_id IS NOT NULL',
        );

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
            'subject' => $book->getTitle(),
            'route' => 'app_plugin_books_book_show',
            'params' => ['id' => $book->getId()],
        ];
    }

    public function isReportableAt(int $locationId): bool
    {
        $allowed = $this->itemFilter->getAllowedItemIds(BookService::ITEM_TYPE);

        return $allowed === null || in_array($locationId, $allowed, true);
    }

    protected function sizes(): array
    {
        return [[self::FREE_AXIS, 800], [400, 600], [350, 525], [200, 300], [100, 150]];
    }
}
