<?php declare(strict_types=1);

namespace Plugin\Boardgames\Tests\Unit\Portability;

use App\Entity\Image;
use App\Entity\User;
use App\Item\Portability\ImportContext;
use App\Item\Portability\PortableImageWriterInterface;
use App\Service\Media\ImageLocationService;
use App\Service\System\PortableImageImporter;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Boardgames\Entity\Game;
use Plugin\Boardgames\Enum\ExternalSource;
use Plugin\Boardgames\Portability\GameContributor;
use Plugin\Boardgames\Repository\GameRepository;
use ReflectionProperty;

class GameContributorTest extends TestCase
{
    public function testExportCarriesTheCatalogFieldsAndBoxPath(): void
    {
        // Arrange
        $game = $this->game(4, 'Catan', 1995);
        $game->setMinPlayers(3);
        $game->setMaxPlayers(4);
        $game->setWeight('2.30');
        $game->setBoxImage(new Image());

        $repo = $this->createStub(GameRepository::class);
        $repo->method('findBy')->willReturn([$game]);

        $images = $this->createStub(PortableImageWriterInterface::class);
        $images->method('addImage')->willReturnCallback(static fn(Image $image, string $hint): string => $hint . '.jpg');

        $contributor = $this->contributor($this->createStub(EntityManagerInterface::class), $repo);

        // Act
        $rows = $contributor->exportItems([4], $images);

        // Assert
        static::assertSame(4, $rows[0]['ref']);
        static::assertSame('Catan', $rows[0]['name']);
        static::assertSame(1995, $rows[0]['year_published']);
        static::assertSame(3, $rows[0]['min_players']);
        static::assertSame('2.30', $rows[0]['weight']);
        static::assertSame('images/boardgames/4/box.jpg', $rows[0]['box_image']);
    }

    public function testAMatchingExternalIdResolvesToTheExistingGame(): void
    {
        // Arrange
        $existing = $this->game(77, 'Catan', 1995);
        $repo = $this->createStub(GameRepository::class);
        $repo->method('findByExternalId')->willReturn($existing);

        $contributor = $this->contributor($this->createStub(EntityManagerInterface::class), $repo);
        $rows = [['ref' => 4, 'name' => 'Catan', 'year_published' => 1995, 'external_source' => 'bgg', 'external_id' => '13']];

        // Act
        $result = $contributor->importItems($rows, $this->context());

        // Assert
        static::assertSame([4 => 77], $result->refToItemId);
        static::assertSame(1, $result->matched);
        static::assertSame(0, $result->created);
    }

    public function testWithoutAnExternalIdTheNameAndYearMatch(): void
    {
        // Arrange
        $existing = $this->game(88, 'Catan', 1995);
        $repo = $this->createStub(GameRepository::class);
        $repo->method('findByExternalId')->willReturn(null);
        $repo->method('findByNameAndYear')->willReturn($existing);

        $contributor = $this->contributor($this->createStub(EntityManagerInterface::class), $repo);
        $rows = [['ref' => 4, 'name' => 'Catan', 'year_published' => 1995, 'external_source' => 'manual']];

        // Act
        $result = $contributor->importItems($rows, $this->context());

        // Assert
        static::assertSame([4 => 88], $result->refToItemId);
        static::assertSame(1, $result->matched);
    }

    public function testAnUnknownGameIsCreatedWithEveryCatalogField(): void
    {
        // Arrange
        $repo = $this->createStub(GameRepository::class);
        $repo->method('findByExternalId')->willReturn(null);
        $repo->method('findByNameAndYear')->willReturn(null);

        $created = null;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$created): void {
            if ($entity instanceof Game) {
                new ReflectionProperty(Game::class, 'id')->setValue($entity, 55);
                $created = $entity;
            }
        });

        $contributor = $this->contributor($em, $repo);
        $rows = [[
            'ref' => 4,
            'name' => 'Wingspan',
            'year_published' => 2019,
            'min_players' => 1,
            'max_players' => 5,
            'min_playtime' => 40,
            'max_playtime' => 70,
            'min_age' => 10,
            'weight' => '2.40',
            'description' => 'Birds.',
            'external_source' => 'bgg',
            'external_id' => '266192',
        ]];

        // Act
        $result = $contributor->importItems($rows, $this->context());

        // Assert
        static::assertSame([4 => 55], $result->refToItemId);
        static::assertSame(1, $result->created);
        static::assertNotNull($created);
        static::assertSame('Wingspan', $created->getName());
        static::assertSame(5, $created->getMaxPlayers());
        static::assertSame(ExternalSource::Bgg, $created->getExternalSource());
        static::assertSame('266192', $created->getExternalId());
    }

    public function testAnUnknownExternalSourceValueFallsBackToManual(): void
    {
        // Arrange
        $repo = $this->createStub(GameRepository::class);
        $repo->method('findByExternalId')->willReturn(null);
        $repo->method('findByNameAndYear')->willReturn(null);

        $created = null;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$created): void {
            if ($entity instanceof Game) {
                new ReflectionProperty(Game::class, 'id')->setValue($entity, 56);
                $created = $entity;
            }
        });

        $contributor = $this->contributor($em, $repo);

        // Act
        $contributor->importItems([['ref' => 9, 'name' => 'Hive', 'external_source' => 'atlas']], $this->context());

        // Assert
        static::assertSame(ExternalSource::Manual, $created?->getExternalSource());
    }

    private function game(int $id, string $name, ?int $year): Game
    {
        $game = new Game();
        new ReflectionProperty(Game::class, 'id')->setValue($game, $id);
        $game->setName($name);
        $game->setYearPublished($year);
        $game->setCreatedBy(1);
        $game->setCreatedAt(new DateTimeImmutable());

        return $game;
    }

    private function context(): ImportContext
    {
        return new ImportContext($this->createStub(PortableImageImporter::class), '/tmp', new User());
    }

    private function contributor(EntityManagerInterface $em, GameRepository $repo): GameContributor
    {
        return new GameContributor($em, $repo, $this->createStub(ImageLocationService::class));
    }
}
