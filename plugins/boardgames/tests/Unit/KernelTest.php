<?php declare(strict_types=1);

namespace Plugin\Boardgames\Tests\Unit;

use App\Entity\User;
use App\Item\Tag\TagService;
use App\Publisher\PluginSettings\Resolver;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\Item\AssociationService;
use App\Service\Item\SeedEventScope;
use App\Service\Security\SecretBox;
use PHPUnit\Framework\TestCase;
use Plugin\Boardgames\Kernel;
use Plugin\Boardgames\Repository\GameRepository;
use Plugin\Boardgames\Service\FixtureBoxService;
use Plugin\Boardgames\Service\FixtureCatalogService;
use Plugin\Boardgames\Service\GameService;
use Plugin\Boardgames\Service\PledgeService;
use Plugin\Boardgames\Service\ShelfService;
use Plugin\Boardgames\Service\TileService;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class KernelTest extends TestCase
{
    public function testSelfSeedStandsDownWhenTheCatalogAlreadyHoldsGames(): void
    {
        // Arrange
        $gameService = $this->createMock(GameService::class);
        $gameService->expects(self::never())->method('createManual');
        $output = new BufferedOutput();

        // Act
        $this->makeKernel(catalogSize: 20, gameService: $gameService)->loadPostExtendFixtures($output);

        // Assert
        self::assertStringContainsString(
            'already seeded',
            $output->fetch(),
            'The guard must count rows in the repository, never the filtered GameService list: that list runs '
            . 'through the item filter chain, which resolves empty on the CLI, so a filtered guard seeds a '
            . 'second full catalog on every reset.',
        );
    }

    public function testSelfSeedSkipsWithoutAnAdminToOwnTheRows(): void
    {
        // Arrange
        $gameService = $this->createMock(GameService::class);
        $gameService->expects(self::never())->method('createManual');
        $output = new BufferedOutput();

        // Act
        $this->makeKernel(catalogSize: 0, gameService: $gameService, hasAdmin: false)->loadPostExtendFixtures($output);

        // Assert
        self::assertStringContainsString('no admin user found', $output->fetch());
    }

    private function makeKernel(
        int $catalogSize,
        GameService $gameService,
        bool $hasAdmin = true,
    ): Kernel {
        $gameRepository = $this->createStub(GameRepository::class);
        $gameRepository->method('count')->willReturn($catalogSize);

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($hasAdmin ? $this->makeAdmin() : null);

        return new Kernel(
            $this->createStub(UrlGeneratorInterface::class),
            $this->createStub(Environment::class),
            $this->createStub(TranslatorInterface::class),
            $gameService,
            $this->createStub(ShelfService::class),
            $this->createStub(PledgeService::class),
            $this->createStub(TileService::class),
            $this->createStub(TagService::class),
            $this->createStub(AssociationService::class),
            $this->createStub(EventRepository::class),
            $userRepository,
            $this->createStub(SeedEventScope::class),
            $this->createStub(FixtureBoxService::class),
            new FixtureCatalogService(),
            $gameRepository,
            $this->createStub(Resolver::class),
            $this->createStub(SecretBox::class),
            null,
        );
    }

    private function makeAdmin(): User
    {
        $admin = $this->createStub(User::class);
        $admin->method('getId')->willReturn(1);

        return $admin;
    }
}
