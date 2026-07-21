<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Glossary\Entity\Glossary;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Regression cover for the member suggestion / moderator review flow, which the item refactor
 * leaves untouched: a member's edit becomes a suggestion, a moderator's edit is written directly,
 * and a moderator can apply a suggestion or approve a pending entry.
 */
class GlossarySuggestionFlowTest extends WebTestCase
{
    private const string MODERATOR_EMAIL = 'Admin@example.org';
    private const string MEMBER_EMAIL = 'Adem.Lane@example.org';

    public function testMemberEditBecomesASuggestion(): void
    {
        // Arrange
        $client = static::createClient();
        $entry = $this->approvedEntry($client);
        $original = (string) $entry->getExplanation();
        $client->loginUser($this->user($client, self::MEMBER_EMAIL));

        // Act
        $this->submitEdit($client, (int) $entry->getId(), 'a member proposal');

        // Assert
        $reloaded = $this->reload($client, (int) $entry->getId());
        self::assertSame($original, $reloaded->getExplanation());
        self::assertCount(1, $reloaded->getSuggestions());
        self::assertSame('a member proposal', $reloaded->getSuggestions()[0]->value);
    }

    public function testModeratorEditIsWrittenDirectly(): void
    {
        // Arrange
        $client = static::createClient();
        $entry = $this->approvedEntry($client);
        $client->loginUser($this->user($client, self::MODERATOR_EMAIL));

        // Act
        $this->submitEdit($client, (int) $entry->getId(), 'a moderator rewrite');

        // Assert
        $reloaded = $this->reload($client, (int) $entry->getId());
        self::assertSame('a moderator rewrite', $reloaded->getExplanation());
        self::assertSame([], $reloaded->getSuggestions());
    }

    public function testModeratorAppliesASuggestion(): void
    {
        // Arrange
        $client = static::createClient();
        $entry = $this->approvedEntry($client);
        $id = (int) $entry->getId();

        $client->loginUser($this->user($client, self::MEMBER_EMAIL));
        $this->submitEdit($client, $id, 'proposal to apply');

        $client->loginUser($this->user($client, self::MODERATOR_EMAIL));
        $hash = $this->reload($client, $id)->getSuggestions()[0]->getHash();
        $crawler = $client->request('GET', '/en/glossary/suggestion/list/' . $id);
        $token = (string) $crawler->filter('a[href$="/suggestion/apply/' . $id . '/' . $hash . '"]')->attr('data-csrf-token');

        // Act
        $client->request('POST', '/en/glossary/suggestion/apply/' . $id . '/' . $hash, ['_token' => $token]);

        // Assert
        $this->assertResponseRedirects();
        $reloaded = $this->reload($client, $id);
        self::assertSame('proposal to apply', $reloaded->getExplanation());
        self::assertSame([], $reloaded->getSuggestions());
    }

    public function testModeratorApprovesAPendingEntry(): void
    {
        // Arrange
        $client = static::createClient();
        $pending = $this->entry($client, false);
        $id = (int) $pending->getId();
        $client->loginUser($this->user($client, self::MODERATOR_EMAIL));

        $crawler = $client->request('GET', '/en/glossary/approval/list/' . $id);
        $token = (string) $crawler->filter('a[href$="/approval/approve/' . $id . '"]')->attr('data-csrf-token');

        // Act
        $client->request('POST', '/en/glossary/approval/approve/' . $id, ['_token' => $token]);

        // Assert
        $this->assertResponseRedirects();
        self::assertTrue($this->reload($client, $id)->getApproved());
    }

    private function submitEdit(KernelBrowser $client, int $id, string $explanation): void
    {
        $crawler = $client->request('GET', '/en/glossary/edit/' . $id);
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('.box form')->form();
        $form['glossary[explanation]'] = $explanation;
        $client->submit($form);
        $this->assertResponseRedirects();
    }

    private function approvedEntry(KernelBrowser $client): Glossary
    {
        return $this->entry($client, true);
    }

    private function entry(KernelBrowser $client, bool $approved): Glossary
    {
        $entry = $this->em($client)->getRepository(Glossary::class)->findOneBy(['approved' => $approved]);
        if (!$entry instanceof Glossary) {
            self::fail('Required glossary fixture entry missing');
        }

        return $entry;
    }

    private function reload(KernelBrowser $client, int $id): Glossary
    {
        $em = $this->em($client);
        $em->clear();
        $entry = $em->getRepository(Glossary::class)->find($id);
        if (!$entry instanceof Glossary) {
            self::fail('Glossary entry vanished');
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
