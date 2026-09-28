<?php declare(strict_types=1);

namespace Module\Ballot\Tests\Functional;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Ballot\Contract\BallotInterface;
use Module\Ballot\Contract\BallotRequest;
use Module\Ballot\Contract\Candidate;
use Module\Ballot\Tests\Stub\BlindfoldVisibilityFilter;
use Module\Ballot\Tests\Stub\ClaimedPage;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Module\Members;

final class BallotPageTest extends WebTestCase
{
    private ?User $member = null;

    public function testTheNotificationAppearsOnlyOnceAMemberHasSomethingToVoteOn(): void
    {
        // Arrange
        $client = static::createClient();
        $client->loginUser($this->member($client));
        $crawler = $client->request('GET', '/en/ballots');
        self::assertCount(0, $crawler->filter('.notifications-dropdown a[href$="/en/ballots"]'), 'nothing awaits this member');

        // Act
        $this->openBallot($client, 'test.page');

        // Assert
        $crawler = $client->request('GET', '/en/ballots');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.notifications-dropdown a[href$="/en/ballots"]'));
    }

    public function testTheIndexListsAnOpenBallotAndTheDetailPageOffersTheForm(): void
    {
        // Arrange
        $client = static::createClient();
        $client->loginUser($this->member($client));
        $id = $this->openBallot($client, 'test.page');

        // Act
        $crawler = $client->request('GET', '/en/ballots');

        // Assert
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('a[href$="/en/ballots/' . $id . '"]'));

        $crawler = $client->request('GET', '/en/ballots/' . $id);
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('input[name="candidates[]"]'));
    }

    public function testTheIndexLinksAClaimedBallotToItsOwnersPage(): void
    {
        // Arrange
        $client = static::createClient();
        $client->loginUser($this->member($client));
        $id = $this->openBallot($client, ClaimedPage::PURPOSE);

        // Act
        $crawler = $client->request('GET', '/en/ballots');

        // Assert
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('a[href="/elsewhere/' . $id . '"]'));
        self::assertCount(0, $crawler->filter('a[href$="/en/ballots/' . $id . '"]'));
    }

    public function testTheModulePageOfAClaimedBallotRedirectsToItsOwnersPage(): void
    {
        // Arrange
        $client = static::createClient();
        $client->loginUser($this->member($client));
        $id = $this->openBallot($client, ClaimedPage::PURPOSE);

        // Act
        $client->request('GET', '/en/ballots/' . $id);

        // Assert
        self::assertResponseRedirects('/elsewhere/' . $id);
    }

    public function testAMemberCanCastAVoteFromThePage(): void
    {
        // Arrange
        $client = static::createClient();
        $client->loginUser($this->member($client));
        $id = $this->openBallot($client, 'test.page');
        $crawler = $client->request('GET', '/en/ballots/' . $id);

        $token = (string) $crawler->filter('form[action$="/vote"] input[name="_token"]')->attr('value');

        // Act
        $client->request('POST', '/en/ballots/' . $id . '/vote', ['_token' => $token, 'candidates' => ['b']]);

        // Assert
        self::assertResponseRedirects();
        $view = self::getContainer()
            ->get(BallotInterface::class)
            ->view($id, (int) $this->member($client)->getId());
        self::assertSame(['b'], $view?->viewerSelection);
    }

    public function testAnOutOfScopeBallotIsA404(): void
    {
        // Arrange
        $client = static::createClient();
        $client->disableReboot();
        $client->loginUser($this->member($client));
        $id = $this->openBallot($client, 'test.page');
        $client->request('GET', '/en/ballots/' . $id);
        self::assertResponseIsSuccessful();

        // Act
        self::getContainer()->get(BlindfoldVisibilityFilter::class)->hiddenBallotIds = [$id];
        $client->request('GET', '/en/ballots/' . $id);

        // Assert
        self::assertResponseStatusCodeSame(404);
    }

    private function openBallot(KernelBrowser $client, string $purpose): int
    {
        return self::getContainer()
            ->get(BallotInterface::class)
            ->open(
                new BallotRequest(
                    $purpose,
                    [new Candidate('a', 'Alpha'), new Candidate('b', 'Beta')],
                    new DateTimeImmutable('+7 days'),
                    (int) $this->member($client)->getId(),
                ),
            );
    }

    private function member(KernelBrowser $client): User
    {
        $this->member ??= new Members($client->getContainer()->get(EntityManagerInterface::class))->member('Voter');

        return $this->member;
    }
}
