<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Functional;

use App\DataFixtures\EventFixture;
use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Entity\User;
use App\Item\TypeRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Photos\Service\PhotoService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ItemBoxExitTest extends WebTestCase
{
    private const string HOST = 'weiqi.meetagain.local';
    private const string ORGANIZER_EMAIL = 'admin@example.org';
    private const string EVENT_TITLE = EventFixture::ONLINE_SIMULTANEOUS;

    public function testPhotosAreNoLongerAnOfferedItemType(): void
    {
        // Arrange
        $client = static::createClient();
        $client->request('GET', '/en/events', server: $this->host());

        // Act
        $registry = $client->getContainer()->get(TypeRegistry::class);

        // Assert
        static::assertTrue($registry->has('film'));
        static::assertFalse($registry->has(PhotoService::ITEM_TYPE));
    }

    public function testTheAttachPickerStillOffersFilmsButNoLongerPhotos(): void
    {
        // Arrange
        $client = $this->signedInOrganizer();

        // Act
        $client->request('GET', '/en/event/' . $this->eventId($client), server: $this->host());

        // Assert
        $this->assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        static::assertStringContainsString('data-item-attach-panel="film"', $content);
        static::assertStringNotContainsString('data-item-attach-panel="' . PhotoService::ITEM_TYPE . '"', $content);
    }

    public function testTheWishlistTakesAFilmButRefusesAPhoto(): void
    {
        // Arrange
        $client = $this->signedInOrganizer();

        // Act & Assert
        $this->addToWishlist($client, 'film', $this->filmId($client));
        static::assertResponseRedirects();

        $this->addToWishlist($client, PhotoService::ITEM_TYPE, $this->photoId($client));
        static::assertResponseStatusCodeSame(404);
    }

    public function testThePollCreateRouteTakesAFilmButRefusesAPhoto(): void
    {
        // Arrange
        $client = $this->signedInOrganizer();

        // Act & Assert
        $client->request('GET', '/en/voting/poll/create/' . $this->eventId($client) . '/film', server: $this->host());
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/en/voting/poll/create/' . $this->eventId($client) . '/photo', server: $this->host());
        static::assertResponseStatusCodeSame(404);
    }

    private function addToWishlist(KernelBrowser $client, string $itemType, int $itemId): void
    {
        $token = $this->token($client, 'wishlist_add' . $itemType . $itemId);
        $client->request('POST', '/en/wishlist/add/' . $itemType . '/' . $itemId, ['_token' => $token], server: $this->host());
    }

    private function token(KernelBrowser $client, string $tokenId): string
    {
        $client->request('GET', '/en/events', server: $this->host());
        $session = $client->getRequest()->getSession();
        $session->set('_csrf/' . $tokenId, 'a-token');
        $session->save();

        return 'a-token';
    }

    private function signedInOrganizer(): KernelBrowser
    {
        $client = static::createClient();
        $user = $client->getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->findOneBy(['email' => self::ORGANIZER_EMAIL]);
        if (!$user instanceof User) {
            self::fail('Required fixture user missing: ' . self::ORGANIZER_EMAIL);
        }
        $client->loginUser($user);

        return $client;
    }


    /** @return array<string, string> */
    private function host(): array
    {
        return ['HTTP_HOST' => self::HOST];
    }

    private function eventId(KernelBrowser $client): int
    {
        $translation = $this->em($client)->getRepository(EventTranslation::class)->findOneBy(['title' => self::EVENT_TITLE]);
        if ($translation === null || !$translation->getEvent() instanceof Event) {
            self::fail('Required fixture event missing: ' . self::EVENT_TITLE);
        }

        return (int) $translation->getEvent()->getId();
    }

    private function filmId(KernelBrowser $client): int
    {
        $id = $this->em($client)->getConnection()->fetchOne('SELECT MIN(id) FROM plg_films_film');
        if ($id === false || $id === null) {
            self::fail('Required fixture film missing');
        }

        return (int) $id;
    }

    private function photoId(KernelBrowser $client): int
    {
        $id = $this->em($client)->getConnection()->fetchOne('SELECT MIN(id) FROM plg_photos_photo');
        if ($id === false || $id === null) {
            self::fail('Required fixture photo missing');
        }

        return (int) $id;
    }

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }
}
