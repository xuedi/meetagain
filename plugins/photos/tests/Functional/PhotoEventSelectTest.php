<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Functional;

use App\Entity\ItemTagAssignment;
use App\Entity\User;
use App\Repository\EventItemAssociationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Photos\Service\PhotoService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class PhotoEventSelectTest extends WebTestCase
{
    private const string HOST = 'photo.meetagain.local';
    private const string MEMBER_EMAIL = 'Kari.Rasmussen@example.org';
    private const int PHOTO_ID = 20;
    private const int OWN_EVENT = 21;
    private const int SIBLING_EVENT = 20;
    private const int FOREIGN_EVENT = 10;

    public function testTheSelectIsPreselectedWithThePhotosCurrentEvent(): void
    {
        // Arrange
        $client = $this->signedInOwner();

        // Act
        $crawler = $this->editPage($client);

        // Assert
        static::assertSame([(string) self::OWN_EVENT], $crawler->filter('#photo_edit_event option[selected]')->each(
            static fn(Crawler $node): string => (string) $node->attr('value'),
        ));
    }

    public function testTheSelectOffersNoEventTheCallerMayNotSee(): void
    {
        // Arrange
        $client = $this->signedInOwner();

        // Act
        $offered = $this->editPage($client)->filter('#photo_edit_event option')->each(
            static fn(Crawler $node): string => (string) $node->attr('value'),
        );

        // Assert
        static::assertContains((string) self::OWN_EVENT, $offered);
        static::assertNotContains((string) self::FOREIGN_EVENT, $offered);
    }

    public function testChoosingAnotherEventMovesThePhoto(): void
    {
        // Arrange
        $client = $this->signedInOwner();

        // Act
        $this->submitEdit($client, (string) self::SIBLING_EVENT);

        // Assert
        static::assertSame([self::SIBLING_EVENT], $this->eventIds($client));
    }

    public function testEmptyingTheSelectClearsTheEvent(): void
    {
        // Arrange
        $client = $this->signedInOwner();

        // Act
        $this->submitEdit($client, '');

        // Assert
        static::assertSame([], $this->eventIds($client));
    }

    public function testTheManagedEventTagIsNeitherOfferedNorStrippedBySaving(): void
    {
        // Arrange
        $client = $this->signedInOwner();
        $this->submitEdit($client, (string) self::SIBLING_EVENT);
        $offered = $this->editPage($client)->filter('#photo_edit_itemTags input')->each(
            static fn(Crawler $node): string => (string) $node->attr('value'),
        );
        $managed = array_values(array_diff($this->tagIds($client), $offered));
        static::assertNotSame([], $managed, 'The move should have left a managed tag on the photo');

        // Act
        $this->submitEdit($client, (string) self::SIBLING_EVENT);

        // Assert
        static::assertSame($managed, array_values(array_intersect($this->tagIds($client), $managed)));
    }

    private function submitEdit(KernelBrowser $client, string $eventValue): void
    {
        $form = $this->editPage($client)->selectButton('photo_edit[submit]')->form();
        $form['photo_edit[event]'] = $eventValue;
        $client->submit($form);
        $this->assertResponseRedirects('/en/photos/' . self::PHOTO_ID);
    }

    private function editPage(KernelBrowser $client): Crawler
    {
        $crawler = $client->request('GET', '/en/photos/' . self::PHOTO_ID . '/edit', server: ['HTTP_HOST' => self::HOST]);
        $this->assertResponseIsSuccessful();

        return $crawler;
    }

    /** @return list<int> */
    private function eventIds(KernelBrowser $client): array
    {
        return $client->getContainer()
            ->get(EventItemAssociationRepository::class)
            ->findEventIdsByItem(PhotoService::ITEM_TYPE, self::PHOTO_ID);
    }

    /** @return list<string> */
    private function tagIds(KernelBrowser $client): array
    {
        $assignments = $client->getContainer()->get(EntityManagerInterface::class)
            ->getRepository(ItemTagAssignment::class)
            ->findBy(['itemType' => PhotoService::ITEM_TYPE, 'itemId' => self::PHOTO_ID]);

        return array_map(static fn(ItemTagAssignment $assignment): string => (string) $assignment->getTagId(), $assignments);
    }

    private function signedInOwner(): KernelBrowser
    {
        $client = static::createClient();
        $user = $client->getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->findOneBy(['email' => self::MEMBER_EMAIL]);
        if (!$user instanceof User) {
            self::fail('Required fixture user missing: ' . self::MEMBER_EMAIL);
        }
        $client->loginUser($user);

        return $client;
    }
}
