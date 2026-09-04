<?php declare(strict_types=1);

namespace Plugin\Boardgames\Service;

use App\Comment\CommentService;
use App\Enum\ImageType;
use App\Enum\ItemAction;
use App\Item\ActionDispatcher;
use App\Item\AdminFilterService;
use App\Item\FilterService;
use App\Service\Media\ImageLocationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Boardgames\Entity\Game;
use Plugin\Boardgames\Enum\ExternalSource;
use Plugin\Boardgames\Repository\GameRepository;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

readonly class GameService
{
    public const string ITEM_TYPE = 'boardgame';

    public function __construct(
        private EntityManagerInterface $em,
        private GameRepository $gameRepo,
        private FilterService $itemFilter,
        private AdminFilterService $adminItemFilter,
        private ActionDispatcher $dispatcher,
        private BoxImageService $boxImageService,
        private CommentService $comments,
        private ImageLocationService $imageLocationService,
    ) {}

    public function createFromMetadata(GameMetadata $metadata, int $userId): Game
    {
        if ($this->gameRepo->findByExternalId($metadata->externalId, $metadata->source->value) !== null) {
            throw new RuntimeException('boardgames_game.flash_already_exists');
        }

        $game = new Game();
        $game->setName($metadata->name);
        $game->setOriginalName($metadata->originalName);
        $game->setYearPublished($metadata->yearPublished);
        $game->setMinPlayers($metadata->minPlayers);
        $game->setMaxPlayers($metadata->maxPlayers);
        $game->setBestPlayerCount($metadata->bestPlayerCount);
        $game->setMinPlaytime($metadata->minPlaytime);
        $game->setMaxPlaytime($metadata->maxPlaytime);
        $game->setMinAge($metadata->minAge);
        $game->setWeight($metadata->weight);
        $game->setDescription($metadata->description);
        $game->setExternalSource($metadata->source);
        $game->setExternalId($metadata->externalId);
        $game->setCreatedBy($userId);
        $game->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($game);
        $this->em->flush();

        if ($metadata->boxImageUrl !== null) {
            $box = $this->boxImageService->downloadAndSave($metadata->boxImageUrl, $userId);
            if ($box !== null) {
                $game->setBoxImage($box);
                $this->em->persist($game);
                $this->em->flush();
                $this->imageLocationService->addLocation((int) $box->getId(), ImageType::PluginBoardgamesBox, (int) $game->getId());
            }
        }

        $this->dispatcher->dispatch(ItemAction::Created, self::ITEM_TYPE, (int) $game->getId());

        return $game;
    }

    public function createManual(
        string $name,
        ?int $yearPublished,
        ?int $minPlayers,
        ?int $maxPlayers,
        ?int $minPlaytime,
        ?int $maxPlaytime,
        ?string $weight,
        ?string $description,
        int $userId,
    ): Game {
        $game = new Game();
        $game->setName($name);
        $game->setYearPublished($yearPublished);
        $game->setMinPlayers($minPlayers);
        $game->setMaxPlayers($maxPlayers);
        $game->setMinPlaytime($minPlaytime);
        $game->setMaxPlaytime($maxPlaytime);
        $game->setWeight($weight);
        $game->setDescription($description);
        $game->setExternalSource(ExternalSource::Manual);
        $game->setCreatedBy($userId);
        $game->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($game);
        $this->em->flush();

        $this->dispatcher->dispatch(ItemAction::Created, self::ITEM_TYPE, (int) $game->getId());

        return $game;
    }

    public function update(Game $game, ?UploadedFile $boxFile, int $userId): Game
    {
        $previousBoxId = null;
        $newBox = null;
        if ($boxFile !== null) {
            $previousBoxId = $game->getBoxImage()?->getId();
            $newBox = $this->boxImageService->uploadFromFile($boxFile, $userId);
            if ($newBox === null) {
                throw new RuntimeException('boardgames_game.flash_invalid_image');
            }
            $game->setBoxImage($newBox);
        }

        $this->em->persist($game);
        $this->em->flush();

        if ($newBox !== null) {
            if ($previousBoxId !== null && $previousBoxId !== $newBox->getId()) {
                $this->imageLocationService->removeLocation($previousBoxId, ImageType::PluginBoardgamesBox, (int) $game->getId());
            }

            $this->imageLocationService->addLocation((int) $newBox->getId(), ImageType::PluginBoardgamesBox, (int) $game->getId());
        }

        $this->dispatcher->dispatch(ItemAction::Updated, self::ITEM_TYPE, (int) $game->getId());

        return $game;
    }

    public function delete(Game $game): void
    {
        $gameId = (int) $game->getId();
        $box = $game->getBoxImage();
        if ($box !== null) {
            $this->imageLocationService->removeLocation((int) $box->getId(), ImageType::PluginBoardgamesBox, $gameId);
        }

        $this->em->remove($game);
        $this->em->flush();

        $this->comments->deleteAllFor(self::ITEM_TYPE, $gameId);
        $this->dispatcher->dispatch(ItemAction::Deleted, self::ITEM_TYPE, $gameId);
    }

    /** @return list<Game> */
    public function getList(): array
    {
        return $this->gameRepo->findAllOrdered($this->itemFilter->getAllowedItemIds(self::ITEM_TYPE));
    }

    /** @return list<Game> */
    public function getManagedList(): array
    {
        return $this->gameRepo->findAllOrdered($this->adminItemFilter->getAllowedItemIds(self::ITEM_TYPE));
    }

    /** @return list<Game> */
    public function search(string $term): array
    {
        return $this->gameRepo->searchByName($term, $this->itemFilter->getAllowedItemIds(self::ITEM_TYPE));
    }

    public function get(int $id): ?Game
    {
        return $this->gameRepo->findOneAllowed($id, $this->itemFilter->getAllowedItemIds(self::ITEM_TYPE));
    }

    public function getManaged(int $id): ?Game
    {
        return $this->gameRepo->findOneAllowed($id, $this->adminItemFilter->getAllowedItemIds(self::ITEM_TYPE));
    }

    public function getAttached(int $id): ?Game
    {
        return $this->gameRepo->findOneAllowed($id, null);
    }
}
