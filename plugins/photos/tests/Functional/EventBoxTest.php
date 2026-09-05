<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Functional;

use App\DataFixtures\EventFixture;
use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Entity\ItemTag;
use App\Entity\ItemTagAssignment;
use App\Entity\User;
use App\Publisher\PluginSettings\GenericStore;
use App\Repository\EventItemAssociationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Service\PhotoService;
use Plugin\Photos\ValueObject\Config;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class EventBoxTest extends WebTestCase
{
    private const string HOST = 'weiqi.meetagain.local';
    private const string EVENT_TITLE = EventFixture::BEGINNER_WORKSHOP;
    private const string MEMBER_EMAIL = 'Maxwell.Tan@example.org';

    public function testThePluginBoxTakesOverTheEventImageBox(): void
    {
        // Arrange
        $client = static::createClient();
        $client->loginUser($this->user($client, self::MEMBER_EMAIL));

        // Act
        $client->request('GET', '/en/event/' . $this->eventId($client), server: $this->host());

        // Assert
        $content = (string) $client->getResponse()->getContent();
        static::assertStringContainsString('/photos/event/' . $this->eventId($client) . '/upload', $content);
        static::assertStringNotContainsString('/image/event/' . $this->eventId($client) . '/modal', $content);
    }

    public function testTheCoreImageBoxComesBackWhenTheEventBoxIsSwitchedOff(): void
    {
        // Arrange
        $client = static::createClient();
        $this->storeConfig($client, new Config()->setEventBox(false));
        $client->loginUser($this->user($client, self::MEMBER_EMAIL));

        // Act
        $client->request('GET', '/en/event/' . $this->eventId($client), server: $this->host());

        // Assert
        $content = (string) $client->getResponse()->getContent();
        static::assertStringContainsString('/image/event/' . $this->eventId($client) . '/modal', $content);
        static::assertStringNotContainsString('/photos/event/' . $this->eventId($client) . '/upload', $content);
    }

    public function testAnUploadCreatesThePhotoTheAssociationAndTheDateTagAtOnce(): void
    {
        // Arrange
        $client = static::createClient();
        $client->loginUser($this->user($client, self::MEMBER_EMAIL));

        // Act
        $photoId = $this->upload($client);

        // Assert
        static::assertContains(
            $this->eventId($client),
            $client->getContainer()->get(EventItemAssociationRepository::class)->findEventIdsByItem(PhotoService::ITEM_TYPE, $photoId),
        );
        $tag = $this->dateTag($client);
        static::assertNotNull($tag);
        static::assertTrue($tag->isManaged());
        static::assertSame($this->eventDate($client), $tag->getLabel('en', 'en'));
        static::assertContains((int) $tag->getId(), $this->assignedTagIds($client, $photoId));
    }

    public function testTheDateTagFacetsTheListDownToThatEventsPhotos(): void
    {
        // Arrange
        $client = static::createClient();
        $client->loginUser($this->user($client, self::MEMBER_EMAIL));
        $photoId = $this->upload($client);
        $tag = $this->dateTag($client);
        static::assertNotNull($tag);

        // Act
        $crawler = $client->request('GET', '/en/photos?tag[]=' . $tag->getId(), server: $this->host());

        // Assert
        $shown = $crawler->filter('[data-item-gallery-slide]')->each(
            static fn($node): string => (string) $node->attr('data-item-gallery-url'),
        );
        static::assertSame(['/en/photos/' . $photoId], $shown);
    }

    public function testAMemberMayNotUploadWhileMemberUploadsAreOff(): void
    {
        // Arrange
        $client = static::createClient();
        $this->storeConfig($client, new Config()->setMemberUploads(false));
        $client->loginUser($this->user($client, self::MEMBER_EMAIL));

        // Act
        $client->request('POST', '/en/photos/event/' . $this->eventId($client) . '/upload', server: $this->host());

        // Assert
        static::assertResponseStatusCodeSame(403);
    }

    private function upload(KernelBrowser $client): int
    {
        $crawler = $client->request('GET', '/en/event/' . $this->eventId($client), server: $this->host());
        $token = (string) $crawler->filter('input[name="event_upload[_token]"]')->attr('value');

        $client->request(
            'POST',
            '/en/photos/event/' . $this->eventId($client) . '/upload',
            ['event_upload' => ['_token' => $token]],
            ['event_upload' => ['files' => [new UploadedFile($this->picture(), 'harbour.jpg', 'image/jpeg', null, true)]]],
            $this->host(),
        );
        $this->assertResponseRedirects('/en/event/' . $this->eventId($client));

        $photo = $this->em($client)->getRepository(Photo::class)->findOneBy([], ['id' => 'DESC']);
        static::assertInstanceOf(Photo::class, $photo);

        return (int) $photo->getId();
    }

    private function picture(): string
    {
        $path = sys_get_temp_dir() . '/photos_event_box_' . uniqid() . '.jpg';
        $image = imagecreatetruecolor(120, 90);
        imagefill($image, 0, 0, imagecolorallocate($image, 12, 84, 160));
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        return $path;
    }

    private function dateTag(KernelBrowser $client): ?ItemTag
    {
        foreach ($this->em($client)->getRepository(ItemTag::class)->findBy(['itemType' => PhotoService::ITEM_TYPE, 'managed' => true]) as $tag) {
            if ($tag->getLabel('en', 'en') === $this->eventDate($client)) {
                return $tag;
            }
        }

        return null;
    }

    /** @return list<int> */
    private function assignedTagIds(KernelBrowser $client, int $photoId): array
    {
        $assignments = $this->em($client)->getRepository(ItemTagAssignment::class)->findBy([
            'itemType' => PhotoService::ITEM_TYPE,
            'itemId' => $photoId,
        ]);

        return array_map(static fn(ItemTagAssignment $assignment): int => (int) $assignment->getTagId(), $assignments);
    }

    private function storeConfig(KernelBrowser $client, Config $config): void
    {
        $client->getContainer()->get(GenericStore::class)->save('photos', $config, null);
    }

    private function user(KernelBrowser $client, string $email): User
    {
        $user = $this->em($client)->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            self::fail('Required fixture user missing: ' . $email);
        }

        return $user;
    }

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }


    /** @return array<string, string> */
    private function host(): array
    {
        return ['HTTP_HOST' => self::HOST];
    }

    private function eventId(KernelBrowser $client): int
    {
        return (int) $this->event($client)->getId();
    }

    private function eventDate(KernelBrowser $client): string
    {
        return $this->event($client)->getStart()->format('Y-m-d');
    }

    private function event(KernelBrowser $client): Event
    {
        $translation = $this->em($client)->getRepository(EventTranslation::class)->findOneBy(['title' => self::EVENT_TITLE]);
        if ($translation === null || !$translation->getEvent() instanceof Event) {
            self::fail('Required fixture event missing: ' . self::EVENT_TITLE);
        }

        return $translation->getEvent();
    }
}
