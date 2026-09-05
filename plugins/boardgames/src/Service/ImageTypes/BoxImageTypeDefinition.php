<?php declare(strict_types=1);

namespace Plugin\Boardgames\Service\ImageTypes;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use App\Service\Media\ImageTypes\AbstractImageTypeDefinition;
use Doctrine\DBAL\Connection;
use Plugin\Boardgames\Repository\GameRepository;

final class BoxImageTypeDefinition extends AbstractImageTypeDefinition
{
    public function __construct(
        ImageLocationRepository $repo,
        Connection $connection,
        private readonly GameRepository $gameRepository,
    ) {
        parent::__construct($repo, $connection);
    }

    public function getType(): ImageType
    {
        return ImageType::PluginBoardgamesBox;
    }

    protected function sizes(): array
    {
        return [[self::FREE_AXIS, 800], [400, 400], [350, 350], [200, 200]];
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'app_plugin_boardgames_game_show', 'params' => ['id' => $locationId]];
    }

    public function discoverImageIds(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT box_image_id AS image_id, id AS location_id FROM plg_boardgames_game WHERE box_image_id IS NOT NULL',
        );

        return array_map(static fn(array $r) => [
            'imageId' => (int) $r['image_id'],
            'locationId' => (int) $r['location_id'],
        ], $rows);
    }

    public function locate(Image $image): ?array
    {
        $game = $this->gameRepository->findOneBy(['boxImage' => $image]);
        if ($game === null) {
            return null;
        }

        return [
            'label' => sprintf('Board game box: %s', $game->getName() ?? ''),
            'route' => 'app_plugin_boardgames_game_show',
            'params' => ['id' => $game->getId()],
        ];
    }
}
