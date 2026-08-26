<?php declare(strict_types=1);

namespace Plugin\Books\Tests\Functional;

use App\Circulation\CirculationService;
use App\Entity\CirculationCopy;
use App\Entity\CirculationRequest;
use App\Entity\User;
use App\Enum\CirculationCopyStatus;
use App\Enum\CirculationHandoverStatus;
use App\Enum\CirculationLedgerEntryType;
use App\Enum\CirculationRequestStatus;
use App\Publisher\PluginSettings\Resolver;
use App\Repository\CirculationCopyRepository;
use App\Repository\CirculationHandoverRepository;
use App\Repository\CirculationLedgerEntryRepository;
use App\Repository\CirculationRequestRepository;
use App\Repository\UserRepository;
use Module\Trust\Contract\TrustLevel;
use Plugin\Books\Service\BookService;
use Plugin\Books\ValueObject\Config;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CirculationTest extends WebTestCase
{
    private const string BOOK_HOST = 'books.meetagain.local';
    private const string OWNER_EMAIL = 'Kaden.Scott@example.org';
    private const string DONOR_EMAIL = 'Florence.Shaw@example.org';
    private const string FIRST_IN_LINE_EMAIL = 'Genevieve.Mclean@example.org';
    private const string SECOND_IN_LINE_EMAIL = 'Freya.Browning@example.org';
    private const string PASSWORD = '1234';

    public function testTheHappyPathMovesACopyAndLeavesACompleteLedger(): void
    {
        // Arrange
        $client = static::createClient();
        $this->enableCirculation($client);
        $bookId = $this->firstListedBookWithoutCopies($client);

        // Act
        $this->login($client, self::DONOR_EMAIL);
        $this->post($client, '/en/circulation/book/' . $bookId . '/donate', 'app_circulation_donatebook' . $bookId, ['label' => 'blue hardcover']);
        $this->login($client, self::FIRST_IN_LINE_EMAIL);
        $this->post($client, '/en/circulation/book/' . $bookId . '/request', 'app_circulation_requestbook' . $bookId);
        $this->login($client, self::SECOND_IN_LINE_EMAIL);
        $this->post($client, '/en/circulation/book/' . $bookId . '/request', 'app_circulation_requestbook' . $bookId);

        $copy = $this->copyFor($client, $bookId);
        $this->login($client, self::DONOR_EMAIL);
        $this->post($client, '/en/circulation/copy/' . $copy->getId() . '/finished', 'app_circulation_copy_finished' . $copy->getId());

        $handover = $this->openHandover($client, $copy);
        $this->post($client, '/en/circulation/handover/' . $handover . '/confirm', 'app_circulation_handover_confirm' . $handover);
        $this->login($client, self::FIRST_IN_LINE_EMAIL);
        $this->post($client, '/en/circulation/handover/' . $handover . '/confirm', 'app_circulation_handover_confirm' . $handover);

        // Assert
        $moved = $this->copyFor($client, $bookId);
        static::assertSame(CirculationCopyStatus::Held, $moved->getStatus());
        static::assertSame(self::FIRST_IN_LINE_EMAIL, $moved->getHolder()?->getEmail());
        static::assertSame(CirculationHandoverStatus::Completed, $this->handovers($client)->find($handover)?->getStatus());
        static::assertSame(
            [CirculationRequestStatus::Fulfilled, CirculationRequestStatus::Waiting],
            array_map(
                static fn($request): CirculationRequestStatus => $request->getStatus(),
                [$this->requestOf($client, $bookId, self::FIRST_IN_LINE_EMAIL), $this->requestOf($client, $bookId, self::SECOND_IN_LINE_EMAIL)],
            ),
        );
        static::assertNotSame([], $this->ledgerTypes($client, CirculationLedgerEntryType::HandoverCompleted));
    }

    public function testEveryDashboardTabRenders(): void
    {
        // Arrange
        $client = static::createClient();
        $this->enableCirculation($client);
        $bookId = $this->firstListedBookWithoutCopies($client);
        $this->reachAHandover($client, $bookId);
        $this->login($client, self::OWNER_EMAIL);

        foreach (['shelf', 'waiting', 'handovers', 'activity', 'stats'] as $tab) {
            // Act
            $crawler = $client->request('GET', '/en/circulation/book?tab=' . $tab, server: $this->host());

            // Assert
            $this->assertResponseIsSuccessful();
            static::assertCount(1, $crawler->filter('li.is-active a[href*="tab=' . $tab . '"]'), 'Tab ' . $tab . ' is not marked active.');
        }
    }

    public function testAParticipantOpensTheHandoverPageAndSeesTheChatForm(): void
    {
        // Arrange
        $client = static::createClient();
        $this->enableCirculation($client);
        $bookId = $this->firstListedBookWithoutCopies($client);
        $handover = $this->reachAHandover($client, $bookId);

        // Act
        $this->login($client, self::FIRST_IN_LINE_EMAIL);
        $crawler = $client->request('GET', '/en/circulation/handover/' . $handover, server: $this->host());

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertCount(1, $crawler->filter('form[action*="/comment/circulation_handover/' . $handover . '"]'));
        static::assertCount(1, $crawler->filter('form[action$="/handover/' . $handover . '/confirm"]'));
    }

    public function testTheTrustTabAppearsOnlyWhenBothCheckboxesAreTicked(): void
    {
        // Arrange
        $client = static::createClient();
        $this->enableCirculation($client);
        $bookId = $this->firstListedBookWithoutCopies($client);
        $this->login($client, self::OWNER_EMAIL);
        $withoutTrust = $client->request('GET', '/en/circulation/book', server: $this->host());

        // Act
        $this->enableTrust($client);
        $withTrust = $client->request('GET', '/en/circulation/book?tab=trust', server: $this->host());

        // Assert
        static::assertCount(0, $withoutTrust->filter('a[href*="tab=trust"]'));
        $this->assertResponseIsSuccessful();
        static::assertCount(1, $withTrust->filter('li.is-active a[href*="tab=trust"]'));
    }

    public function testVouchingReturnsToTheTrustTabRatherThanTheShelf(): void
    {
        // Arrange
        $client = static::createClient();
        $this->enableTrust($client);
        $this->login($client, self::OWNER_EMAIL);
        $client->request('GET', '/en/circulation/book?tab=trust', server: $this->host());
        $context = static::getContainer()->get(CirculationService::class)->getContext(BookService::ITEM_TYPE);
        $targetId = (int) $this->user(self::DONOR_EMAIL)->getId();

        // Act
        $this->post($client, '/en/trust/vouch', 'trust_vouch' . $targetId, [
            'context' => $context,
            'user' => (string) $targetId,
            'level' => TrustLevel::Trusted->value,
        ]);

        // Assert
        static::assertStringContainsString('tab=trust', (string) $client->getResponse()->headers->get('location'));
    }

    public function testTheHandoverChatIsPrivateToItsTwoParticipants(): void
    {
        // Arrange
        $client = static::createClient();
        $this->enableCirculation($client);
        $bookId = $this->firstListedBookWithoutCopies($client);
        $handover = $this->reachAHandover($client, $bookId);

        // Act
        $this->login($client, self::OWNER_EMAIL);
        $this->post($client, '/en/comment/circulation_handover/' . $handover, 'app_comment_createcirculation_handover' . $handover, ['content' => 'Butting in']);

        // Assert
        static::assertCount(0, static::getContainer()->get('App\Repository\CommentRepository')->findForTarget('circulation_handover', $handover));
    }

    public function testAStrangerCannotOpenTheHandoverPage(): void
    {
        // Arrange
        $client = static::createClient();
        $this->enableCirculation($client);
        $bookId = $this->firstListedBookWithoutCopies($client);
        $handover = $this->reachAHandover($client, $bookId);

        // Act
        $this->login($client, self::SECOND_IN_LINE_EMAIL);
        $client->request('GET', '/en/circulation/handover/' . $handover, server: $this->host());

        // Assert
        $this->assertResponseStatusCodeSame(404);
    }

    public function testCancellingAHandoverLeavesTheCopyWithTheGiverAndTheRequesterQueued(): void
    {
        // Arrange
        $client = static::createClient();
        $this->enableCirculation($client);
        $bookId = $this->firstListedBookWithoutCopies($client);
        $handover = $this->reachAHandover($client, $bookId);

        // Act
        $this->login($client, self::DONOR_EMAIL);
        $this->post($client, '/en/circulation/handover/' . $handover . '/cancel', 'app_circulation_handover_cancel' . $handover);

        // Assert
        $copy = $this->copyFor($client, $bookId);
        static::assertSame(CirculationCopyStatus::Available, $copy->getStatus());
        static::assertSame(self::DONOR_EMAIL, $copy->getHolder()?->getEmail());
        static::assertSame(CirculationRequestStatus::Waiting, $this->requestOf($client, $bookId, self::FIRST_IN_LINE_EMAIL)?->getStatus());
    }

    public function testWithTheCheckboxOffEverySurfaceIsGone(): void
    {
        // Arrange
        $client = static::createClient();
        $this->disableCirculation($client);
        $bookId = $this->firstListedBookWithoutCopies($client);
        $this->login($client, self::DONOR_EMAIL);

        // Act
        $detail = $client->request('GET', '/en/books/' . $bookId, server: $this->host());
        $dashboardStatus = $this->statusOf($client, '/en/circulation/book');
        $this->post($client, '/en/circulation/book/' . $bookId . '/donate', 'app_circulation_donatebook' . $bookId, ['label' => 'never stored']);

        // Assert
        static::assertCount(0, $detail->filter('.circulation-panel'));
        static::assertSame(404, $dashboardStatus);
        static::assertSame([], $this->copies($client)->findByItem(BookService::ITEM_TYPE, $bookId));
    }

    public function testAnotherGroupsHostShowsNoneOfTheseCopies(): void
    {
        // Arrange
        $client = static::createClient();
        $this->enableCirculation($client);
        $bookId = $this->firstListedBookWithoutCopies($client);
        $this->login($client, self::DONOR_EMAIL);
        $this->post($client, '/en/circulation/book/' . $bookId . '/donate', 'app_circulation_donatebook' . $bookId, ['label' => 'blue hardcover']);

        // Act
        $client->request('GET', '/en/circulation/book', server: ['HTTP_HOST' => 'cinema.meetagain.local']);

        // Assert
        static::assertStringNotContainsString('blue hardcover', (string) $client->getResponse()->getContent());
    }

    public function testDeletingTheBookRetiresItsCopiesAndClosesItsHandovers(): void
    {
        // Arrange
        $client = static::createClient();
        $this->enableCirculation($client);
        $bookId = $this->firstListedBookWithoutCopies($client);
        $handover = $this->reachAHandover($client, $bookId);

        // Act
        $book = static::getContainer()->get(BookService::class)->getAttached($bookId);
        static::assertNotNull($book);
        static::getContainer()->get(BookService::class)->delete($book);

        // Assert
        $copies = $this->copies($client)->findByItem(BookService::ITEM_TYPE, $bookId);
        static::assertNotSame([], $copies);
        static::assertSame(CirculationCopyStatus::Retired, $copies[0]->getStatus());
        static::assertSame(CirculationHandoverStatus::Cancelled, $this->handovers($client)->find($handover)?->getStatus());
    }

    private function reachAHandover(KernelBrowser $client, int $bookId): int
    {
        $this->login($client, self::DONOR_EMAIL);
        $this->post($client, '/en/circulation/book/' . $bookId . '/donate', 'app_circulation_donatebook' . $bookId, ['label' => 'blue hardcover']);
        $this->login($client, self::FIRST_IN_LINE_EMAIL);
        $this->post($client, '/en/circulation/book/' . $bookId . '/request', 'app_circulation_requestbook' . $bookId);

        $copy = $this->copyFor($client, $bookId);
        $this->login($client, self::DONOR_EMAIL);
        $this->post($client, '/en/circulation/copy/' . $copy->getId() . '/finished', 'app_circulation_copy_finished' . $copy->getId());

        return $this->openHandover($client, $copy);
    }

    private function openHandover(KernelBrowser $client, CirculationCopy $copy): int
    {
        $handovers = $this->handovers($client)->findByCopyIds([(int) $copy->getId()]);
        static::assertNotSame([], $handovers, 'Marking the copy finished should have opened a handover for the first member in line.');

        return (int) $handovers[0]->getId();
    }

    private function copyFor(KernelBrowser $client, int $bookId): CirculationCopy
    {
        $copies = $this->copies($client)->findByItem(BookService::ITEM_TYPE, $bookId);
        static::assertNotSame([], $copies);

        return $copies[0];
    }

    private function requestOf(KernelBrowser $client, int $bookId, string $email): ?CirculationRequest
    {
        $user = $this->user($email);
        $context = static::getContainer()->get(CirculationService::class)->getContext(BookService::ITEM_TYPE);

        return static::getContainer()->get(CirculationRequestRepository::class)
            ->findOneBy(['context' => $context, 'itemType' => BookService::ITEM_TYPE, 'itemId' => $bookId, 'user' => $user], ['id' => 'DESC']);
    }

    /**
     * @return list<CirculationLedgerEntryType>
     */
    private function ledgerTypes(KernelBrowser $client, CirculationLedgerEntryType $type): array
    {
        $context = static::getContainer()->get(CirculationService::class)->getContext(BookService::ITEM_TYPE);

        return array_map(
            static fn($entry): CirculationLedgerEntryType => $entry->getEntryType(),
            static::getContainer()->get(CirculationLedgerEntryRepository::class)->findOfType($context, $type),
        );
    }

    private function firstListedBookWithoutCopies(KernelBrowser $client): int
    {
        $this->login($client, self::DONOR_EMAIL);
        $crawler = $client->request('GET', '/en/books', server: $this->host());
        $this->assertResponseIsSuccessful();

        $taken = array_map(
            static fn(CirculationCopy $copy): int => $copy->getItemId(),
            static::getContainer()->get(CirculationCopyRepository::class)->findAllOrdered(),
        );

        $listed = $crawler->filter('a[href*="/en/books/"]')->each(static function ($node): int {
            preg_match('~/en/books/(\d+)~', (string) $node->attr('href'), $matches);

            return (int) ($matches[1] ?? 0);
        });

        foreach ($listed as $bookId) {
            if ($bookId > 0 && !in_array($bookId, $taken, true)) {
                return $bookId;
            }
        }

        static::fail('The book list shows no title without circulation copies.');
    }

    private function statusOf(KernelBrowser $client, string $path): int
    {
        $client->request('GET', $path, server: $this->host());

        return $client->getResponse()->getStatusCode();
    }

    private function post(KernelBrowser $client, string $path, string $tokenId, array $parameters = []): void
    {
        $session = $client->getSession();
        $session->set('_csrf/' . $tokenId, 'primed-token');
        $session->save();

        $client->request('POST', $path, $parameters + ['_token' => 'primed-token'], server: $this->host());
    }

    private function enableCirculation(KernelBrowser $client): void
    {
        $this->writeBooksConfigAtEveryScope($client, (new Config())->setCirculation(true));
    }

    private function enableTrust(KernelBrowser $client): void
    {
        $this->writeBooksConfigAtEveryScope($client, (new Config())->setCirculation(true)->setTrustSystem(true));
    }

    private function disableCirculation(KernelBrowser $client): void
    {
        $this->writeBooksConfigAtEveryScope($client, new Config());
    }

    private function writeBooksConfigAtEveryScope(KernelBrowser $client, Config $config): void
    {
        $client->request('GET', '/en/books', server: $this->host());

        $resolver = static::getContainer()->get(Resolver::class);
        $resolver->resolveStore('books', null)?->save('books', $config, null);

        $scopeId = $resolver->resolveScopeId();
        if ($scopeId !== null) {
            $resolver->resolveStore('books', $scopeId)?->save('books', $config, $scopeId);
        }
    }

    private function user(string $email): User
    {
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        static::assertNotNull($user, 'Fixture user ' . $email . ' is missing.');

        return $user;
    }

    private function copies(KernelBrowser $client): CirculationCopyRepository
    {
        return static::getContainer()->get(CirculationCopyRepository::class);
    }

    private function handovers(KernelBrowser $client): CirculationHandoverRepository
    {
        return static::getContainer()->get(CirculationHandoverRepository::class);
    }

    private function login(KernelBrowser $client, string $email): void
    {
        $crawler = $client->request('GET', '/en/login', server: $this->host());
        $form = $crawler->selectButton('Login')->form(['_username' => $email, '_password' => self::PASSWORD]);
        $client->submit($form);
        $client->followRedirect();
    }

    /**
     * @return array<string, string>
     */
    private function host(): array
    {
        return ['HTTP_HOST' => self::BOOK_HOST];
    }
}
