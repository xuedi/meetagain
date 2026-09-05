<?php declare(strict_types=1);

namespace Plugin\Boardgames\Tests\Functional;

use App\Entity\Event;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Boardgames\Entity\Game;
use Plugin\Boardgames\Entity\GameOwnership;
use Plugin\Boardgames\Entity\GamePledge;
use Plugin\Boardgames\Enum\ExternalSource;
use Plugin\Boardgames\Repository\BringRequestRepository;
use Plugin\Boardgames\Repository\GameOwnershipRepository;
use Plugin\Boardgames\Repository\GamePledgeRepository;
use Plugin\Boardgames\Service\GameService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CenterTileTest extends WebTestCase
{
    private const string MEMBER_EMAIL = 'Crystal.Liu@example.org';
    private const string PASSWORD = '1234';
    private const string EVENT_URL = '/en/event/1';
    private const string EVENT_HOST = 'weiqi.meetagain.local';

    public function testAMemberSeesThePledgedGameInTheCenterTile(): void
    {
        // Arrange
        $client = static::createClient([], ['HTTP_HOST' => self::EVENT_HOST]);
        $this->login($client, self::MEMBER_EMAIL);
        $this->seedPledge('Testboard Deluxe');

        // Act
        $crawler = $client->request('GET', self::EVENT_URL);

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertStringContainsString('Testboard Deluxe', $crawler->filter('body')->text());
        static::assertStringContainsString('Games at this meetup', $crawler->filter('body')->text());
    }

    public function testAGuestSeesNoCenterTile(): void
    {
        // Arrange
        $client = static::createClient([], ['HTTP_HOST' => self::EVENT_HOST]);
        $this->seedPledge('Testboard Deluxe');

        // Act
        $crawler = $client->request('GET', self::EVENT_URL);

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertStringNotContainsString('Testboard Deluxe', $crawler->filter('body')->text());
    }

    public function testPledgingWithoutAValidCsrfTokenIsRefused(): void
    {
        // Arrange
        $client = static::createClient([], ['HTTP_HOST' => self::EVENT_HOST]);
        $this->login($client, self::MEMBER_EMAIL);
        $game = $this->seedGame('Testboard Refusal');

        // Act
        $client->request('POST', '/en/boardgames/pledge/1', ['_token' => 'nope', 'game_id' => $game->getId()]);

        // Assert
        $this->assertResponseStatusCodeSame(400);
    }

    public function testDeletingAGameClearsItsOwnershipsPledgesAndRequests(): void
    {
        // Arrange
        $container = static::getContainer();
        $game = $this->seedPledge('Testboard Doomed');
        $gameId = (int) $game->getId();

        // Act
        $container->get(GameService::class)->delete($game);

        // Assert
        static::assertSame([], $container->get(GameOwnershipRepository::class)->findBy(['game' => $gameId]));
        static::assertSame([], $container->get(GamePledgeRepository::class)->findBy(['game' => $gameId]));
        static::assertSame([], $container->get(BringRequestRepository::class)->findBy(['game' => $gameId]));
    }

    private function seedPledge(string $name): Game
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        $game = $this->seedGame($name);
        $member = $container->get(UserRepository::class)->findOneBy(['email' => self::MEMBER_EMAIL]);
        $event = $container->get(EventRepository::class)->find(1);
        static::assertInstanceOf(User::class, $member);
        static::assertInstanceOf(Event::class, $event);

        $ownership = new GameOwnership();
        $ownership->setUser($member);
        $ownership->setGame($game);
        $ownership->setCreatedAt(new DateTimeImmutable());
        $em->persist($ownership);

        $event->addRsvp($member);

        $pledge = new GamePledge();
        $pledge->setEvent($event);
        $pledge->setGame($game);
        $pledge->setUser($member);
        $pledge->setCreatedAt(new DateTimeImmutable());
        $em->persist($pledge);
        $em->flush();

        return $game;
    }

    private function seedGame(string $name): Game
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $game = new Game();
        $game->setName($name);
        $game->setMinPlayers(2);
        $game->setMaxPlayers(4);
        $game->setExternalSource(ExternalSource::Manual);
        $game->setCreatedBy(1);
        $game->setCreatedAt(new DateTimeImmutable());
        $em->persist($game);
        $em->flush();

        return $game;
    }

    private function login(KernelBrowser $client, string $email): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler
            ->selectButton('Login')
            ->form([
                '_username' => $email,
                '_password' => self::PASSWORD,
            ]);
        $client->submit($form);
        $client->followRedirect();
    }
}
