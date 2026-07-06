<?php declare(strict_types=1);

namespace App\Service\Media\ImageTypes;

use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\ImageType;
use App\Repository\CmsBlockRepository;
use App\Repository\ImageLocationRepository;
use Doctrine\DBAL\Connection;

final class CmsGalleryImageTypeDefinition extends AbstractImageTypeDefinition
{
    use ResolvesCmsBlockLocation;

    public function __construct(
        ImageLocationRepository $repo,
        Connection $connection,
        private readonly CmsBlockRepository $cmsBlockRepository,
    ) {
        parent::__construct($repo, $connection);
    }

    public function getType(): ImageType
    {
        return ImageType::CmsGallery;
    }

    protected function sizes(): array
    {
        return [[1024, 768], [350, 263], [210, 140]];
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'app_admin_cms_block_edit', 'params' => ['blockId' => $locationId]];
    }

    public function discoverImageIds(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id, json FROM cms_block WHERE type = ?', [
            CmsBlockType::Gallery->value,
        ]);

        $pairs = [];
        foreach ($rows as $row) {
            $blockId = (int) $row['id'];
            $json = json_decode((string) $row['json'], true) ?? [];
            foreach ($json['images'] ?? [] as $item) {
                $imageId = $item['id'] ?? null;
                if ($imageId !== null) {
                    $pairs[] = ['imageId' => (int) $imageId, 'locationId' => $blockId];
                }
            }
        }

        return $pairs;
    }

    protected function cmsBlockRepository(): CmsBlockRepository
    {
        return $this->cmsBlockRepository;
    }
}
