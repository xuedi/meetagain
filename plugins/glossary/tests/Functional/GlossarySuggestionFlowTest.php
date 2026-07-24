<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Functional;

use App\Entity\ChangeProposal;
use App\Entity\User;
use App\Enum\ChangeProposalStatus;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Glossary\Entity\Glossary;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Regression cover for the member proposal / moderator review flow on the universal change
 * proposal tool: a member's edit becomes a pending proposal, a moderator's edit is written
 * directly, a moderator resolves proposals per field on the core review page, and the proposer
 * can withdraw. The separate new-entry approval flow stays as it was.
 */
class GlossarySuggestionFlowTest extends WebTestCase
{
    private const string MODERATOR_EMAIL = 'Admin@example.org';
    private const string MEMBER_EMAIL = 'Adem.Lane@example.org';

    public function testMemberEditCreatesAPendingProposal(): void
    {
        // Arrange
        $client = static::createClient();
        $entry = $this->approvedEntryWithoutProposals($client);
        $original = (string) $entry->getExplanation();
        $client->loginUser($this->user($client, self::MEMBER_EMAIL));

        // Act
        $this->submitEdit($client, (int) $entry->getId(), 'a member proposal');

        // Assert
        $reloaded = $this->reload($client, (int) $entry->getId());
        self::assertSame($original, $reloaded->getExplanation());
        $proposals = $this->pendingProposals($client, (int) $entry->getId());
        self::assertCount(1, $proposals);
        self::assertSame('a member proposal', $proposals[0]->getChange('explanation')->after);
    }

    public function testModeratorEditIsWrittenDirectly(): void
    {
        // Arrange
        $client = static::createClient();
        $entry = $this->approvedEntryWithoutProposals($client);
        $client->loginUser($this->user($client, self::MODERATOR_EMAIL));

        // Act
        $this->submitEdit($client, (int) $entry->getId(), 'a moderator rewrite');

        // Assert
        $reloaded = $this->reload($client, (int) $entry->getId());
        self::assertSame('a moderator rewrite', $reloaded->getExplanation());
        self::assertCount(0, $this->pendingProposals($client, (int) $entry->getId()));
    }

    public function testModeratorAppliesOneFieldAndDeniesAnother(): void
    {
        // Arrange
        $client = static::createClient();
        $entry = $this->approvedEntryWithoutProposals($client);
        $id = (int) $entry->getId();
        $originalPhrase = (string) $entry->getPhrase();

        $client->loginUser($this->user($client, self::MEMBER_EMAIL));
        $this->submitEdit($client, $id, 'proposal to apply', 'proposed phrase');

        $client->loginUser($this->user($client, self::MODERATOR_EMAIL));
        $proposalId = (int) $this->pendingProposals($client, $id)[0]->getId();
        $crawler = $client->request('GET', '/en/review/proposals/glossary/' . $id);
        $this->assertResponseIsSuccessful();
        $token = (string) $crawler->filter('a[href$="/proposal/' . $proposalId . '/apply/explanation"]')->attr('data-csrf-token');

        // Act
        $client->request('POST', '/en/review/proposal/' . $proposalId . '/apply/explanation', ['_token' => $token]);
        $this->assertResponseRedirects();
        $client->request('POST', '/en/review/proposal/' . $proposalId . '/deny/phrase', ['_token' => $token]);
        $this->assertResponseRedirects();

        // Assert
        $reloaded = $this->reload($client, $id);
        self::assertSame('proposal to apply', $reloaded->getExplanation());
        self::assertSame($originalPhrase, $reloaded->getPhrase());
        self::assertSame(ChangeProposalStatus::Approved, $this->proposal($client, $proposalId)->getStatus());
    }

    public function testProposerCanWithdrawAPendingProposal(): void
    {
        // Arrange
        $client = static::createClient();
        $entry = $this->approvedEntryWithoutProposals($client);
        $id = (int) $entry->getId();

        $client->loginUser($this->user($client, self::MEMBER_EMAIL));
        $this->submitEdit($client, $id, 'to be withdrawn');
        $proposalId = (int) $this->pendingProposals($client, $id)[0]->getId();

        $crawler = $client->request('GET', '/en/review/proposals/glossary/' . $id);
        $this->assertResponseIsSuccessful();
        $token = (string) $crawler->filter('a[href$="/proposal/' . $proposalId . '/withdraw"]')->attr('data-csrf-token');

        // Act
        $client->request('POST', '/en/review/proposal/' . $proposalId . '/withdraw', ['_token' => $token]);

        // Assert
        $this->assertResponseRedirects();
        self::assertSame(ChangeProposalStatus::Withdrawn, $this->proposal($client, $proposalId)->getStatus());
        self::assertCount(0, $this->pendingProposals($client, $id));
    }

    public function testMemberCannotApplyAProposal(): void
    {
        // Arrange
        $client = static::createClient();
        $entry = $this->approvedEntryWithoutProposals($client);
        $id = (int) $entry->getId();

        $client->loginUser($this->user($client, self::MEMBER_EMAIL));
        $this->submitEdit($client, $id, 'a member proposal');
        $proposalId = (int) $this->pendingProposals($client, $id)[0]->getId();

        $crawler = $client->request('GET', '/en/review/proposals/glossary/' . $id);
        $token = (string) $crawler->filter('a[href$="/proposal/' . $proposalId . '/withdraw"]')->attr('data-csrf-token');

        // Act
        $client->request('POST', '/en/review/proposal/' . $proposalId . '/apply/explanation', ['_token' => $token]);

        // Assert
        $this->assertResponseStatusCodeSame(403);
        self::assertCount(1, $this->pendingProposals($client, $id));
    }

    public function testApplyWithInvalidCsrfTokenIsRejected(): void
    {
        // Arrange
        $client = static::createClient();
        $entry = $this->approvedEntryWithoutProposals($client);
        $id = (int) $entry->getId();

        $client->loginUser($this->user($client, self::MEMBER_EMAIL));
        $this->submitEdit($client, $id, 'a member proposal');
        $proposalId = (int) $this->pendingProposals($client, $id)[0]->getId();

        $client->loginUser($this->user($client, self::MODERATOR_EMAIL));

        // Act
        $client->request('POST', '/en/review/proposal/' . $proposalId . '/apply/explanation', ['_token' => 'broken']);

        // Assert
        $this->assertResponseStatusCodeSame(403);
        self::assertCount(1, $this->pendingProposals($client, $id));
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

    private function submitEdit(KernelBrowser $client, int $id, string $explanation, ?string $phrase = null): void
    {
        $crawler = $client->request('GET', '/en/glossary/edit/' . $id);
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('.box form')->form();
        $form['glossary[explanation]'] = $explanation;
        if ($phrase !== null) {
            $form['glossary[phrase]'] = $phrase;
        }
        $client->submit($form);
        $this->assertResponseRedirects();
    }

    private function approvedEntryWithoutProposals(KernelBrowser $client): Glossary
    {
        $entries = $this->em($client)->getRepository(Glossary::class)->findBy(['approved' => true]);
        foreach ($entries as $entry) {
            if ($this->pendingProposals($client, (int) $entry->getId()) === []) {
                return $entry;
            }
        }

        self::fail('No approved glossary fixture entry without pending proposals');
    }

    private function entry(KernelBrowser $client, bool $approved): Glossary
    {
        $entry = $this->em($client)->getRepository(Glossary::class)->findOneBy(['approved' => $approved]);
        if (!$entry instanceof Glossary) {
            self::fail('Required glossary fixture entry missing');
        }

        return $entry;
    }

    /** @return list<ChangeProposal> */
    private function pendingProposals(KernelBrowser $client, int $targetId): array
    {
        return $this->em($client)->getRepository(ChangeProposal::class)->findBy([
            'targetType' => 'glossary',
            'targetId' => $targetId,
            'status' => ChangeProposalStatus::Pending,
        ]);
    }

    private function proposal(KernelBrowser $client, int $id): ChangeProposal
    {
        $em = $this->em($client);
        $em->clear();
        $proposal = $em->getRepository(ChangeProposal::class)->find($id);
        if (!$proposal instanceof ChangeProposal) {
            self::fail('Change proposal vanished');
        }

        return $proposal;
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
