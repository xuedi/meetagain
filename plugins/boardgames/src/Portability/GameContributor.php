<?php declare(strict_types=1);

namespace Plugin\Boardgames\Portability;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Item\Portability\ContributorInterface;
use App\Item\Portability\ImportContext;
use App\Item\Portability\ImportResult;
use App\Item\Portability\PortableImageWriterInterface;
use App\Service\Media\ImageLocationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Plugin\Boardgames\Entity\Game;
use Plugin\Boardgames\Enum\ExternalSource;
use Plugin\Boardgames\Repository\GameRepository;
use Plugin\Boardgames\Service\GameService;

readonly class GameContributor implements ContributorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private GameRepository $gameRepo,
        private ImageLocationService $imageLocationService,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'boardgames';
    }

    #[Override]
    public function getItemType(): string
    {
        return GameService::ITEM_TYPE;
    }

    #[Override]
    public function exportItems(array $itemIds, PortableImageWriterInterface $images): array
    {
        $rows = [];

        foreach ($this->gameRepo->findBy(['id' => $itemIds]) as $game) {
            $rows[] = [
                'ref' => (int) $game->getId(),
                'name' => $game->getName(),
                'original_name' => $game->getOriginalName(),
                'year_published' => $game->getYearPublished(),
                'min_players' => $game->getMinPlayers(),
                'max_players' => $game->getMaxPlayers(),
                'best_player_count' => $game->getBestPlayerCount(),
                'min_playtime' => $game->getMinPlaytime(),
                'max_playtime' => $game->getMaxPlaytime(),
                'min_age' => $game->getMinAge(),
                'weight' => $game->getWeight(),
                'description' => $game->getDescription(),
                'external_source' => $game->getExternalSource()->value,
                'external_id' => $game->getExternalId(),
                'box_image' => $game->getBoxImage() instanceof Image
                    ? $images->addImage($game->getBoxImage(), 'images/boardgames/' . $game->getId() . '/box')
                    : null,
            ];
        }

        return $rows;
    }

    #[Override]
    public function importItems(array $rows, ImportContext $context): ImportResult
    {
        $refToItem = [];
        $created = 0;
        $matched = 0;
        $imageLocations = [];

        foreach ($rows as $row) {
            $ref = (int) ($row['ref'] ?? 0);

            $existing = $this->findExisting($row);
            if ($existing instanceof Game) {
                $refToItem[$ref] = $existing;
                ++$matched;
                continue;
            }

            $game = new Game();
            $game->setName((string) ($row['name'] ?? ''));
            $game->setOriginalName($this->nullableString($row['original_name'] ?? null));
            $game->setYearPublished($this->nullableInt($row['year_published'] ?? null));
            $game->setMinPlayers($this->nullableInt($row['min_players'] ?? null));
            $game->setMaxPlayers($this->nullableInt($row['max_players'] ?? null));
            $game->setBestPlayerCount($this->nullableInt($row['best_player_count'] ?? null));
            $game->setMinPlaytime($this->nullableInt($row['min_playtime'] ?? null));
            $game->setMaxPlaytime($this->nullableInt($row['max_playtime'] ?? null));
            $game->setMinAge($this->nullableInt($row['min_age'] ?? null));
            $game->setWeight($this->nullableString($row['weight'] ?? null));
            $game->setDescription($this->nullableString($row['description'] ?? null));
            $game->setExternalSource(ExternalSource::tryFrom((string) ($row['external_source'] ?? '')) ?? ExternalSource::Manual);
            $game->setExternalId($this->nullableString($row['external_id'] ?? null));
            $game->setCreatedBy((int) $context->getSystemUser()->getId());
            $game->setCreatedAt(new DateTimeImmutable());

            $box = $context->importImage($this->nullableString($row['box_image'] ?? null), ImageType::PluginBoardgamesBox);
            if ($box instanceof Image) {
                $game->setBoxImage($box);
                $imageLocations[] = [$box, $game];
            }

            $this->em->persist($game);
            $refToItem[$ref] = $game;
            ++$created;
        }

        $this->em->flush();

        foreach ($imageLocations as [$image, $game]) {
            $this->imageLocationService->addLocation((int) $image->getId(), ImageType::PluginBoardgamesBox, (int) $game->getId());
        }

        return new ImportResult(
            refToItemId: array_map(static fn(Game $game): int => (int) $game->getId(), $refToItem),
            created: $created,
            matched: $matched,
        );
    }

    /** @param array<string, mixed> $row */
    private function findExisting(array $row): ?Game
    {
        $externalId = $this->nullableString($row['external_id'] ?? null);
        $externalSource = $this->nullableString($row['external_source'] ?? null);
        if ($externalId !== null && $externalSource !== null && $externalSource !== ExternalSource::Manual->value) {
            $byExternal = $this->gameRepo->findByExternalId($externalId, $externalSource);
            if ($byExternal instanceof Game) {
                return $byExternal;
            }
        }

        $name = $this->nullableString($row['name'] ?? null);
        if ($name === null) {
            return null;
        }

        return $this->gameRepo->findByNameAndYear($name, $this->nullableInt($row['year_published'] ?? null));
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
