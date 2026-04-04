<?php declare(strict_types=1);

namespace App\Service\Media\ImageLocations;

use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\ImageType;

final class CmsCardImageLocationProvider extends AbstractImageLocationProvider
{
    public function getType(): ImageType
    {
        return ImageType::CmsCardImage;
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'app_admin_cms_block_edit', 'params' => ['blockId' => $locationId]];
    }

    public function discoverImageIds(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, json FROM cms_block WHERE type = ?',
            [CmsBlockType::TrioCards->value],
        );

        $pairs = [];
        foreach ($rows as $row) {
            $blockId = (int) $row['id'];
            $json = json_decode((string) $row['json'], true) ?? [];
            foreach ($json['cards'] ?? [] as $card) {
                $cardImage = $card['image'] ?? null;
                $imageId = is_array($cardImage) ? ($cardImage['id'] ?? null) : null;
                if ($imageId !== null) {
                    $pairs[] = ['imageId' => (int) $imageId, 'locationId' => $blockId];
                }
            }
        }

        return $pairs;
    }
}
