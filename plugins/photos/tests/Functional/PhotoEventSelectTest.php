<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Functional;

use App\DataFixtures\EventFixture;
use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Entity\ItemTagAssignment;
use App\Entity\User;
use App\Repository\EventItemAssociationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Photos\Entity\PhotoTranslation;
use Plugin\Photos\Service\PhotoService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class PhotoEventSelectTest extends WebTestCase
{
    private const string HOST = 'weiqi.meetagain.local';
    private const string MEMBER_EMAIL = 'Kari.Rasmussen@example.org';
    private const string PHOTO_TITLE = 'Night market';
    private const string OWN_EVENT_TITLE = EventFixture::BERLIN_TOURNAMENT;
    private const string SIBLING_EVENT_TITLE = EventFixture::WEEKLY_GO_STUDY;

    public function testTheSelectIsPreselectedWithThePhotosCurrentEvent(): void
    {
        // Arrange
        $client = $this->signedInOwner();

        // Act
        $crawler = $this->editPage($client);

        // Assert
        static::assertSame([(string) $this->eventId($client, self::OWN_EVENT_TITLE)], $crawler->filter('#photo_edit_event option[selected]')->each(
            static fn(Crawler $node): string => (string) $node->attr('value'),
        ));
    }

    public function testChoosingAnotherEventMovesThePhoto(): void
    {
        // Arrange
        $client = $this->signedInOwner();

        // Act
        $this->submitEdit($client, (string) $this->eventId($client, self::SIBLING_EVENT_TITLE));

        // Assert
        static::assertSame([$this->eventId($client, self::SIBLING_EVENT_TITLE)], $this->eventIds($client));
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
        $this->submitEdit($client, (string) $this->eventId($client, self::SIBLING_EVENT_TITLE));
        $offered = $this->editPage($client)->filter('#photo_edit_itemTags input')->each(
            static fn(Crawler $node): string => (string) $node->attr('value'),
        );
        $managed = array_values(array_diff($this->tagIds($client), $offered));
        static::assertNotSame([], $managed, 'The move should have left a managed tag on the photo');

        // Act
        $this->submitEdit($client, (string) $this->eventId($client, self::SIBLING_EVENT_TITLE));

        // Assert
        static::assertSame($managed, array_values(array_intersect($this->tagIds($client), $managed)));
    }

    private function submitEdit(KernelBrowser $client, string $eventValue): void
    {
        $form = $this->editPage($client)->selectButton('photo_edit[submit]')->form();
        $form['photo_edit[event]'] = $eventValue;
        $client->submit($form);
        $this->assertResponseRedirects('/en/photos/' . $this->photoId($client));
    }

    private function editPage(KernelBrowser $client): Crawler
    {
        $crawler = $client->request('GET', '/en/photos/' . $this->photoId($client) . '/edit', server: $this->host());
        $this->assertResponseIsSuccessful();

        return $crawler;
    }

    /** @return list<int> */
    private function eventIds(KernelBrowser $client): array
    {
        return $client->getContainer()
            ->get(EventItemAssociationRepository::class)
            ->findEventIdsByItem(PhotoService::ITEM_TYPE, $this->photoId($client));
    }

    /** @return list<string> */
    private function tagIds(KernelBrowser $client): array
    {
        $assignments = $client->getContainer()->get(EntityManagerInterface::class)
            ->getRepository(ItemTagAssignment::class)
            ->findBy(['itemType' => PhotoService::ITEM_TYPE, 'itemId' => $this->photoId($client)]);

        return array_map(static fn(ItemTagAssignment $assignment): string => (string) $assignment->getTagId(), $assignments);
    }


    /** @return array<string, string> */
    private function host(): array
    {
        return ['HTTP_HOST' => self::HOST];
    }

    private function photoId(KernelBrowser $client): int
    {
        $translation = $this->em($client)->getRepository(PhotoTranslation::class)->findOneBy(['title' => self::PHOTO_TITLE]);
        if ($translation === null || $translation->getPhoto() === null) {
            self::fail('Required fixture photo missing: ' . self::PHOTO_TITLE);
        }

        return (int) $translation->getPhoto()->getId();
    }

    private function eventId(KernelBrowser $client, string $title): int
    {
        $translation = $this->em($client)->getRepository(EventTranslation::class)->findOneBy(['title' => $title]);
        if ($translation === null || !$translation->getEvent() instanceof Event) {
            self::fail('Required fixture event missing: ' . $title);
        }

        return (int) $translation->getEvent()->getId();
    }

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
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
