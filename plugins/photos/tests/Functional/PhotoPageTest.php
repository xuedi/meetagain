<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Functional;

use App\Entity\User;
use App\Publisher\PluginSettings\GenericStore;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\ValueObject\Config;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PhotoPageTest extends WebTestCase
{
    private const string PHOTO_HOST = 'cinema.meetagain.local';
    private const string STEWARD_EMAIL = 'Admin@example.org';
    private const string STRANGER_EMAIL = 'Phoenix.Baker@example.org';

    public function testTheListIsPublicAndRendersThroughTheSharedItemLayout(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/photos', server: $this->host());

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertStringContainsString('data-item-list-scope="photo"', (string) $client->getResponse()->getContent());
    }

    public function testTheListDefaultsToTheGalleryMode(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $client->request('GET', '/en/photos', server: $this->host());

        // Assert
        static::assertCount(1, $crawler->filter('[data-item-gallery]'));
        static::assertCount(1, $crawler->filter('a.is-link[href$="/item/photo/view/gallery"]'));
    }

    public function testTheDetailPageIsPublicAndShowsTheCameraPanel(): void
    {
        // Arrange
        $client = static::createClient();
        $photo = $this->listedPhoto($client);

        // Act
        $crawler = $client->request('GET', '/en/photos/' . $photo->getId(), server: $this->host());

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertStringContainsString('Camera settings', $crawler->text());
        static::assertStringContainsString($photo->getCameraLabel(), $crawler->text());
    }

    public function testTheCameraPanelIsHiddenWhenTheSettingIsOff(): void
    {
        // Arrange
        $client = static::createClient();
        $photo = $this->listedPhoto($client);
        $this->storeConfig($client, new Config()->setShowCameraMeta(false));

        // Act
        $crawler = $client->request('GET', '/en/photos/' . $photo->getId(), server: $this->host());

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertStringNotContainsString('Camera settings', $crawler->text());
    }

    public function testOnlyASignedInVisitorGetsTheCommentForm(): void
    {
        // Arrange
        $client = static::createClient();
        $photo = $this->listedPhoto($client);

        // Act
        $asGuest = $client->request('GET', '/en/photos/' . $photo->getId(), server: $this->host());
        $client->loginUser($this->user($client, self::STRANGER_EMAIL));
        $asMember = $client->request('GET', '/en/photos/' . $photo->getId(), server: $this->host());

        // Assert
        static::assertCount(0, $asGuest->filter('#replyInput'));
        static::assertCount(1, $asMember->filter('#replyInput'));
    }

    public function testAGuestIsSentToTheLoginBeforeUploading(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/photos/add', server: $this->host());

        // Assert
        $this->assertResponseRedirects();
        static::assertStringContainsString('login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testAMemberMayOpenTheUploadFormWhileMemberUploadsAreOn(): void
    {
        // Arrange
        $client = static::createClient();
        $client->loginUser($this->user($client, self::STRANGER_EMAIL));

        // Act
        $client->request('GET', '/en/photos/add', server: $this->host());

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testMemberUploadsOffLeavesTheFormToStewardsOnly(): void
    {
        // Arrange
        $client = static::createClient();
        $this->storeConfig($client, new Config()->setMemberUploads(false));
        $client->loginUser($this->user($client, self::STRANGER_EMAIL));

        // Act
        $client->request('GET', '/en/photos/add', server: $this->host());

        // Assert
        $this->assertResponseStatusCodeSame(403);
    }

    public function testMemberUploadsOffStillLetsAStewardUpload(): void
    {
        // Arrange
        $client = static::createClient();
        $this->storeConfig($client, new Config()->setMemberUploads(false));
        $client->loginUser($this->user($client, self::STEWARD_EMAIL));

        // Act
        $client->request('GET', '/en/photos/add', server: $this->host());

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testTheUploaderMayOpenTheEditForm(): void
    {
        // Arrange
        $client = static::createClient();
        $photo = $this->listedPhoto($client);
        $client->loginUser($this->owner($client, $photo));

        // Act
        $client->request('GET', '/en/photos/' . $photo->getId() . '/edit', server: $this->host());

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testAStrangerMayNotEditSomeoneElsesPhoto(): void
    {
        // Arrange
        $client = static::createClient();
        $photo = $this->listedPhoto($client);
        $client->loginUser($this->user($client, self::STRANGER_EMAIL));

        // Act
        $client->request('GET', '/en/photos/' . $photo->getId() . '/edit', server: $this->host());

        // Assert
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAStewardMayEditAnyPhoto(): void
    {
        // Arrange
        $client = static::createClient();
        $photo = $this->listedPhoto($client);
        $client->loginUser($this->user($client, self::STEWARD_EMAIL));

        // Act
        $client->request('GET', '/en/photos/' . $photo->getId() . '/edit', server: $this->host());

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testAStrangerMayNotDeleteSomeoneElsesPhoto(): void
    {
        // Arrange
        $client = static::createClient();
        $photo = $this->listedPhoto($client);
        $client->loginUser($this->user($client, self::STRANGER_EMAIL));

        // Act
        $client->request('POST', '/en/photos/' . $photo->getId() . '/delete', ['_token' => 'irrelevant'], server: $this->host());

        // Assert
        $this->assertResponseStatusCodeSame(403);
    }

    public function testDeletingWithoutAValidTokenIsRejected(): void
    {
        // Arrange
        $client = static::createClient();
        $photo = $this->listedPhoto($client);
        $client->loginUser($this->owner($client, $photo));

        // Act
        $client->request('POST', '/en/photos/' . $photo->getId() . '/delete', ['_token' => 'not-the-token'], server: $this->host());

        // Assert
        $this->assertResponseStatusCodeSame(400);
    }

    public function testTheUploaderMayDeleteTheirOwnPhoto(): void
    {
        // Arrange
        $client = static::createClient();
        $photo = $this->listedPhoto($client);
        $photoId = (int) $photo->getId();
        $client->loginUser($this->owner($client, $photo));
        $token = (string) $client->request('GET', '/en/photos/' . $photoId, server: $this->host())
            ->filter('a[href$="/photos/' . $photoId . '/delete"]')
            ->attr('data-csrf-token');

        // Act
        $client->request('POST', '/en/photos/' . $photoId . '/delete', ['_token' => $token], server: $this->host());

        // Assert
        $this->assertResponseRedirects();
        static::assertNull($this->em($client)->getRepository(Photo::class)->find($photoId));
    }

    private function listedPhoto(KernelBrowser $client): Photo
    {
        $links = $client->request('GET', '/en/photos', server: $this->host())
            ->filter('[data-item-gallery-slide]')
            ->each(static fn($node): string => (string) $node->attr('data-item-gallery-url'));

        foreach ($links as $href) {
            if (preg_match('#/en/photos/(\d+)$#', $href, $matches) !== 1) {
                continue;
            }

            $photo = $this->em($client)->getRepository(Photo::class)->find((int) $matches[1]);
            if ($photo instanceof Photo && $photo->getMeta() !== null) {
                return $photo;
            }
        }

        self::fail('The photo list showed no photo carrying camera metadata');
    }

    private function storeConfig(KernelBrowser $client, Config $config): void
    {
        $client->getContainer()->get(GenericStore::class)->save('photos', $config, null);
    }

    private function owner(KernelBrowser $client, Photo $photo): User
    {
        $user = $this->em($client)->getRepository(User::class)->find((int) $photo->getCreatedBy());
        if (!$user instanceof User) {
            self::fail('Required photo uploader missing');
        }

        return $user;
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
        return ['HTTP_HOST' => self::PHOTO_HOST];
    }
}
