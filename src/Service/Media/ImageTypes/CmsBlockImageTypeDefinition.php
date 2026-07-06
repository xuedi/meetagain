<?php declare(strict_types=1);

namespace App\Service\Media\ImageTypes;

use App\Enum\ImageType;
use App\Repository\CmsBlockRepository;
use App\Repository\ImageLocationRepository;
use Doctrine\DBAL\Connection;

final class CmsBlockImageTypeDefinition extends AbstractImageTypeDefinition
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
        return ImageType::CmsBlock;
    }

    protected function sizes(): array
    {
        return [[432, 432], [350, 350], [80, 80]];
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'app_admin_cms_block_edit', 'params' => ['blockId' => $locationId]];
    }

    public function discoverImageIds(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT image_id, id AS location_id FROM cms_block WHERE image_id IS NOT NULL');

        return array_map(static fn(array $r) => [
            'imageId' => (int) $r['image_id'],
            'locationId' => (int) $r['location_id'],
        ], $rows);
    }

    protected function cmsBlockRepository(): CmsBlockRepository
    {
        return $this->cmsBlockRepository;
    }
}
