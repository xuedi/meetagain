<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Glossary\Entity\Glossary;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GlossaryPageTest extends WebTestCase
{
    private const string MODERATOR_EMAIL = 'Admin@example.org';
    private const string MEMBER_EMAIL = 'Adem.Lane@example.org';
    private const string GLOSSARY_HOST = 'dragon.meetagain.local';

    public function testListRendersThroughTheSharedItemComponent(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/glossary', server: ['HTTP_HOST' => self::GLOSSARY_HOST]);

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
        $rows = $client->request('GET', '/en/glossary', server: ['HTTP_HOST' => self::GLOSSARY_HOST])
            ->filter('.item-list tbody tr')->count();

        // Act
        $client->request('GET', '/en/item/glossary/view/tiles', server: ['HTTP_HOST' => self::GLOSSARY_HOST]);
        $crawler = $client->request('GET', '/en/glossary', server: ['HTTP_HOST' => self::GLOSSARY_HOST]);

        // Assert
        static::assertGreaterThan(0, $rows);
        static::assertCount(0, $crawler->filter('.item-list table'));
        static::assertSame($rows, $crawler->filter('.item-list .item-cell')->count());
    }

    public function testDisallowedModeFallsBackToList(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/item/glossary/view/gallery', server: ['HTTP_HOST' => self::GLOSSARY_HOST]);
        $crawler = $client->request('GET', '/en/glossary', server: ['HTTP_HOST' => self::GLOSSARY_HOST]);

        // Assert
        static::assertCount(1, $crawler->filter('.item-list table'));
    }

    public function testGuestsSeeNoUnapprovedEntry(): void
    {
        // Arrange
        $client = static::createClient();
        $pendingPhrases = $this->pendingPhrases($client);

        // Act
        $listed = $client->request('GET', '/en/glossary', server: ['HTTP_HOST' => self::GLOSSARY_HOST])
            ->filter('.item-list tbody tr')->each(static fn($row): string => $row->text());

        // Assert
        static::assertNotEmpty($listed);
        foreach ($listed as $row) {
            foreach ($pendingPhrases as $phrase) {
                static::assertStringNotContainsString($phrase, $row);
            }
        }
    }

    public function testModeratorsAlsoSeeUnapprovedEntries(): void
    {
        // Arrange
        $client = static::createClient();
        $guestRows = $client->request('GET', '/en/glossary', server: ['HTTP_HOST' => self::GLOSSARY_HOST])
            ->filter('.item-list tbody tr')->count();
        $client->loginUser($this->user($client, self::MODERATOR_EMAIL));

        // Act
        $crawler = $client->request('GET', '/en/glossary', server: ['HTTP_HOST' => self::GLOSSARY_HOST]);

        // Assert
        static::assertGreaterThan($guestRows, $crawler->filter('.item-list tbody tr')->count());
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

    public function testEditFormOfAnUnapprovedEntryIsNotFoundForMembers(): void
    {
        // Arrange
        $client = static::createClient();
        $pending = $this->entry($client, false);
        $client->loginUser($this->user($client, self::MEMBER_EMAIL));

        // Act
        $client->request('GET', '/en/glossary/edit/' . $pending->getId());

        // Assert
        $this->assertResponseStatusCodeSame(404);
    }

    public function testEditFormOfAnApprovedEntryStaysOpenToMembers(): void
    {
        // Arrange
        $client = static::createClient();
        $approved = $this->entry($client, true);
        $client->loginUser($this->user($client, self::MEMBER_EMAIL));

        // Act
        $client->request('GET', '/en/glossary/edit/' . $approved->getId());

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testEditFormOfAnUnapprovedEntryIsOpenToModerators(): void
    {
        // Arrange
        $client = static::createClient();
        $pending = $this->entry($client, false);
        $client->loginUser($this->user($client, self::MODERATOR_EMAIL));

        // Act
        $client->request('GET', '/en/glossary/edit/' . $pending->getId());

        // Assert
        $this->assertResponseIsSuccessful();
    }

    /** @return list<string> */
    private function pendingPhrases(KernelBrowser $client): array
    {
        $pending = $this->em($client)->getRepository(Glossary::class)->findBy(['approved' => false]);

        return array_values(array_map(static fn(Glossary $entry): string => (string) $entry->getPhrase(), $pending));
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
