<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Glossary\Entity\Glossary;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The rendered cell markup comes from the list-cell provider, which only answers when the glossary
 * plugin is active for the request context - so these assertions count rendered rows rather than
 * cell content, which is what the controller's visibility gating actually decides.
 */
class GlossaryPageTest extends WebTestCase
{
    private const string MODERATOR_EMAIL = 'Admin@example.org';

    public function testListRendersThroughTheSharedItemComponent(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/glossary');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertStringContainsString('data-item-list="glossary"', (string) $client->getResponse()->getContent());
    }

    public function testSwitcherOffersOnlyListAndTiles(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $client->request('GET', '/en/glossary');

        // Assert
        static::assertCount(1, $crawler->filter('a[href$="/item/glossary/view/list"]'));
        static::assertCount(1, $crawler->filter('a[href$="/item/glossary/view/tiles"]'));
        static::assertCount(0, $crawler->filter('a[href$="/item/glossary/view/grid"]'));
        static::assertCount(0, $crawler->filter('a[href$="/item/glossary/view/gallery"]'));
    }

    public function testTilesModeIsReachableAndPersists(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/item/glossary/view/tiles');
        $crawler = $client->request('GET', '/en/glossary');

        // Assert
        static::assertCount(0, $crawler->filter('.item-list table'));
        static::assertSame($this->approvedCount($client), $crawler->filter('.item-list .item-cell')->count());
    }

    public function testDisallowedModeFallsBackToList(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/item/glossary/view/gallery');
        $crawler = $client->request('GET', '/en/glossary');

        // Assert
        static::assertCount(1, $crawler->filter('.item-list table'));
    }

    public function testGuestsSeeOnlyApprovedEntries(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $client->request('GET', '/en/glossary');

        // Assert
        static::assertSame($this->approvedCount($client), $crawler->filter('.item-list tbody tr')->count());
    }

    public function testModeratorsAlsoSeeUnapprovedEntries(): void
    {
        // Arrange
        $client = static::createClient();
        $client->loginUser($this->user($client, self::MODERATOR_EMAIL));

        // Act
        $crawler = $client->request('GET', '/en/glossary');

        // Assert
        static::assertGreaterThan($this->approvedCount($client), $crawler->filter('.item-list tbody tr')->count());
    }

    public function testDetailPageOfAnApprovedEntryIsPublic(): void
    {
        // Arrange
        $client = static::createClient();
        $approved = $this->entry($client, true);

        // Act
        $client->request('GET', '/en/glossary/' . $approved->getId());

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertStringContainsString((string) $approved->getPhrase(), (string) $client->getResponse()->getContent());
    }

    public function testDetailPageOfAnUnapprovedEntryIsNotFoundForGuests(): void
    {
        // Arrange
        $client = static::createClient();
        $pending = $this->entry($client, false);

        // Act
        $client->request('GET', '/en/glossary/' . $pending->getId());

        // Assert
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDetailPageOfAnUnapprovedEntryIsVisibleToModerators(): void
    {
        // Arrange
        $client = static::createClient();
        $pending = $this->entry($client, false);
        $client->loginUser($this->user($client, self::MODERATOR_EMAIL));

        // Act
        $client->request('GET', '/en/glossary/' . $pending->getId());

        // Assert
        $this->assertResponseIsSuccessful();
    }

    private function approvedCount(KernelBrowser $client): int
    {
        return $this->em($client)->getRepository(Glossary::class)->count(['approved' => true]);
    }

    private function entry(KernelBrowser $client, bool $approved): Glossary
    {
        $entry = $this->em($client)->getRepository(Glossary::class)->findOneBy(['approved' => $approved]);
        if (!$entry instanceof Glossary) {
            self::fail('Required glossary fixture entry missing');
        }

        return $entry;
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
}
