<?php declare(strict_types=1);

namespace Module\Suggestion\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Module\Suggestion\Contract\PortableSuggestion;
use Module\Suggestion\Contract\Status;
use Module\Suggestion\Contract\SuggestionInterface;
use Module\Suggestion\Tests\Stub\Target;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Module\Members;

final class ReviewPageTest extends WebTestCase
{
    public function testAStrangerGetsA404(): void
    {
        // Arrange
        $client = static::createClient();
        $id = $this->seed($client, $this->members($client)->member('Proposer'));
        $client->loginUser($this->members($client)->member('Stranger'));

        // Act
        $client->request('GET', '/en/review/suggestions/' . $id);

        // Assert
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheProposerCanWithdrawFromThePage(): void
    {
        // Arrange
        $client = static::createClient();
        $proposer = $this->members($client)->member('Proposer');
        $id = $this->seed($client, $proposer);
        $client->loginUser($proposer);
        $crawler = $client->request('GET', '/en/review/suggestions/' . $id);
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('a[href$="/review/suggestions/' . $id . '/withdraw"]')->attr('data-csrf-token');

        // Act
        $client->request('POST', '/en/review/suggestions/' . $id . '/withdraw', ['_token' => $token]);

        // Assert
        self::assertResponseRedirects();
        self::assertSame(Status::Withdrawn, $this->suggestions($client)->find($id)?->status);
    }

    public function testAReviewerApprovesTheEditedDraftFromThePage(): void
    {
        // Arrange
        $client = static::createClient();
        $client->disableReboot();
        $proposer = $this->members($client)->member('Proposer');
        $reviewer = $this->members($client)->member('Reviewer');
        $target = $client->getContainer()->get(Target::class);
        $target->reviewerIds = [(int) $reviewer->getId()];
        $id = $this->seed($client, $proposer);
        $client->loginUser($reviewer);
        $crawler = $client->request('GET', '/en/review/suggestions/' . $id);
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[action$="/approve"]')->form();
        $form['draft[name]'] = 'Edited';

        // Act
        $client->submit($form);

        // Assert
        self::assertResponseRedirects();
        self::assertSame([['name' => 'Edited', 'proposerId' => (int) $proposer->getId()]], $target->created);
        self::assertSame(Status::Approved, $this->suggestions($client)->find($id)?->status);
    }

    private function seed(KernelBrowser $client, User $proposer): int
    {
        return $this->suggestions($client)->restore(new PortableSuggestion(Target::TYPE, (int) $proposer->getId(), ['name' => 'Cafe']));
    }

    private function suggestions(KernelBrowser $client): SuggestionInterface
    {
        return $client->getContainer()->get(SuggestionInterface::class);
    }

    private function members(KernelBrowser $client): Members
    {
        return new Members($client->getContainer()->get(EntityManagerInterface::class));
    }
}
