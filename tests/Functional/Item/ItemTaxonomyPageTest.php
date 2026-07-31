<?php declare(strict_types=1);

namespace Tests\Functional\Item;

use App\Entity\ChangeProposal;
use App\Entity\User;
use App\Enum\ChangeProposalStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;

class ItemTaxonomyPageTest extends WebTestCase
{
    private const string HOST = 'dragon.meetagain.local';
    private const string MANAGER_EMAIL = 'Admin@example.org';
    private const string MEMBER_EMAIL = 'Phoenix.Baker@example.org';
    private const string PAGE = '/en/item/glossary/taxonomy';
    private const string TARGET_TYPE = 'taxonomy_glossary';

    public function testTheFilterBoxLinksToThePageOnlyForLoggedInVisitors(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $guest = $client->request('GET', '/en/glossary', server: ['HTTP_HOST' => self::HOST]);
        $client->loginUser($this->user($client, self::MEMBER_EMAIL));
        $member = $client->request('GET', '/en/glossary', server: ['HTTP_HOST' => self::HOST]);

        // Assert
        static::assertCount(0, $guest->filter('[data-item-filter] a[href$="/item/glossary/taxonomy"]'));
        static::assertCount(1, $member->filter('[data-item-filter] a[href$="/item/glossary/taxonomy"]'));
    }

    public function testAnUnknownItemTypeIsNotFound(): void
    {
        // Arrange
        $client = static::createClient();
        $client->loginUser($this->user($client, self::MEMBER_EMAIL));

        // Act
        $client->request('GET', '/en/item/nonesuch/taxonomy', server: ['HTTP_HOST' => self::HOST]);

        // Assert
        $this->assertResponseStatusCodeSame(404);
    }

    public function testAGuestIsSentToLogin(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', self::PAGE, server: ['HTTP_HOST' => self::HOST]);

        // Assert
        $this->assertResponseRedirects();
    }

    public function testManagerRenamesADefinitionDirectly(): void
    {
        // Arrange
        $client = static::createClient();
        $client->loginUser($this->user($client, self::MANAGER_EMAIL));
        $crawler = $client->request('GET', self::PAGE, server: ['HTTP_HOST' => self::HOST]);
        $this->assertResponseIsSuccessful();

        // Act
        $form = $crawler->filter('form[name="item_taxonomy_definitions"]')->form();
        $form['item_taxonomy_definitions[categories][0][en]'] = 'Renamed by a manager';
        $client->submit($form);

        // Assert
        $this->assertResponseRedirects();
        $reloaded = $client->request('GET', self::PAGE, server: ['HTTP_HOST' => self::HOST]);
        static::assertSame(
            'Renamed by a manager',
            $reloaded->filter('input[name="item_taxonomy_definitions[categories][0][en]"]')->attr('value'),
        );
        static::assertCount(0, $this->pendingProposals($client), 'A manager writes, never proposes');
    }

    public function testMemberSuggestionIsAppliedByAReviewer(): void
    {
        // Arrange
        $client = static::createClient();
        $client->loginUser($this->user($client, self::MEMBER_EMAIL));
        $crawler = $client->request('GET', self::PAGE, server: ['HTTP_HOST' => self::HOST]);
        $this->assertResponseIsSuccessful();

        // Act
        $client->submit($this->suggestionForm($crawler, 'Suggested by a member'));

        // Assert
        $this->assertResponseRedirects();
        $proposals = $this->pendingProposals($client);
        static::assertCount(1, $proposals);
        static::assertSame('Suggested by a member', $proposals[0]->getChange('category_rename_0_en')->after);

        // Act
        $proposalId = (int) $proposals[0]->getId();
        $client->loginUser($this->user($client, self::MANAGER_EMAIL));
        $review = $client->request('GET', '/en/review/proposals/' . self::TARGET_TYPE . '/' . $proposals[0]->getTargetId(), server: ['HTTP_HOST' => self::HOST]);
        $this->assertResponseIsSuccessful();
        $token = (string) $review->filter('a[href$="/proposal/' . $proposalId . '/apply/category_rename_0_en"]')->attr('data-csrf-token');
        $client->request('POST', '/en/review/proposal/' . $proposalId . '/apply/category_rename_0_en', ['_token' => $token], server: ['HTTP_HOST' => self::HOST]);

        // Assert
        $this->assertResponseRedirects();
        $reloaded = $client->request('GET', self::PAGE, server: ['HTTP_HOST' => self::HOST]);
        static::assertSame(
            'Suggested by a member',
            $reloaded->filter('input[name="item_taxonomy_definitions[categories][0][en]"]')->attr('value'),
        );
        static::assertSame(ChangeProposalStatus::Approved, $this->proposal($client, $proposalId)->getStatus());
    }

    public function testAnUnchangedSuggestionCreatesNoProposal(): void
    {
        // Arrange
        $client = static::createClient();
        $client->loginUser($this->user($client, self::MEMBER_EMAIL));
        $crawler = $client->request('GET', self::PAGE, server: ['HTTP_HOST' => self::HOST]);

        // Act
        $client->submit($crawler->filter('form:has(input[name^="suggest["])')->form());

        // Assert
        $this->assertResponseRedirects();
        static::assertCount(0, $this->pendingProposals($client));
    }

    private function suggestionForm(Crawler $crawler, string $label): Form
    {
        $form = $crawler->filter('form:has(input[name^="suggest["])')->form();
        $form['suggest[category][edit][0]'] = $label;

        return $form;
    }

    /** @return list<ChangeProposal> */
    private function pendingProposals(KernelBrowser $client): array
    {
        return array_values($this->em($client)->getRepository(ChangeProposal::class)->findBy([
            'targetType' => self::TARGET_TYPE,
            'status' => ChangeProposalStatus::Pending,
        ]));
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
