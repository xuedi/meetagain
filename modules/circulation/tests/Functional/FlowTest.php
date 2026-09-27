<?php declare(strict_types=1);

namespace Module\Circulation\Tests\Functional;

use App\Comment\CommentService;
use App\Entity\User;
use App\Enum\ItemAction;
use Doctrine\ORM\EntityManagerInterface;
use Module\Circulation\Contract\CirculationInterface;
use Module\Circulation\Contract\CopyStatus;
use Module\Circulation\Contract\HandoverStatus;
use Module\Circulation\Contract\LedgerEntryType;
use Module\Circulation\Contract\PortableCopy;
use Module\Circulation\Contract\PortableHandover;
use Module\Circulation\Contract\PortableLedgerEntry;
use Module\Circulation\Contract\PortableRequest;
use Module\Circulation\Contract\PortableShelf;
use Module\Circulation\Contract\RequestStatus;
use Module\Circulation\Tests\Stub\ParticipationProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Module\Members;

final class FlowTest extends WebTestCase
{
    private const string TYPE = ParticipationProvider::ITEM_TYPE;
    private const int ITEM = 7;
    private const string ITEM_ACTIONS = 'circulation.test.item_action_dispatcher';

    private KernelBrowser $client;

    private User $donor;

    private User $first;

    private User $second;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $members = new Members(self::getContainer()->get(EntityManagerInterface::class));
        $this->donor = $members->member('Donor');
        $this->first = $members->member('First');
        $this->second = $members->member('Second');
    }

    public function testTheHappyPathMovesACopyAndLeavesACompleteLedger(): void
    {
        // Arrange
        $this->donate();
        $this->requestAs($this->first);
        $this->requestAs($this->second);
        $handover = $this->openHandover($this->copy());

        // Act
        $this->as($this->donor)->post('/en/circulation/handover/' . $handover . '/confirm', 'app_circulation_handover_confirm' . $handover);
        $this->as($this->first)->post('/en/circulation/handover/' . $handover . '/confirm', 'app_circulation_handover_confirm' . $handover);

        // Assert
        $moved = $this->copy();
        self::assertSame(CopyStatus::Held, $moved->status);
        self::assertSame($this->first->getId(), $moved->holderUserId);
        self::assertSame(HandoverStatus::Completed, $this->handover($handover)?->status);
        self::assertSame([RequestStatus::Fulfilled, RequestStatus::Waiting], [
            $this->requestOf($this->first)?->status,
            $this->requestOf($this->second)?->status,
        ]);
        self::assertNotSame([], $this->ledgerOfType(LedgerEntryType::HandoverCompleted));
    }

    public function testAReaderWhoFinishesPassesTheCopyToTheNextInLine(): void
    {
        // Arrange
        $this->donate();
        $this->requestAs($this->first);
        $this->requestAs($this->second);
        $first = $this->openHandover($this->copy());
        $this->as($this->donor)->post('/en/circulation/handover/' . $first . '/confirm', 'app_circulation_handover_confirm' . $first);
        $this->as($this->first)->post('/en/circulation/handover/' . $first . '/confirm', 'app_circulation_handover_confirm' . $first);
        $copy = $this->copy();

        // Act
        $this->as($this->first)->post('/en/circulation/copy/' . $copy->ref . '/finished', 'app_circulation_copy_finished' . $copy->ref);

        // Assert
        $open = array_values(array_filter(
            $this->shelf()->handovers,
            static fn(PortableHandover $handover): bool => $handover->status === HandoverStatus::Open,
        ));
        self::assertCount(1, $open);
        self::assertSame($this->second->getId(), $open[0]->toUserId);
    }

    #[DataProvider('provideTabs')]
    public function testEveryDashboardTabRenders(string $tab): void
    {
        // Arrange
        $this->reachAHandover();
        $this->as($this->donor);

        // Act
        $crawler = $this->client->request('GET', '/en/circulation/' . self::TYPE . '?tab=' . $tab);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('li.is-active a[href*="tab=' . $tab . '"]'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTabs(): iterable
    {
        foreach (['shelf', 'waiting', 'handovers', 'activity', 'stats', 'about'] as $tab) {
            yield $tab => [$tab];
        }
    }

    public function testAParticipantOpensTheHandoverPageAndSeesTheChatForm(): void
    {
        // Arrange
        $handover = $this->reachAHandover();

        // Act
        $crawler = $this->as($this->first)->client->request('GET', '/en/circulation/handover/' . $handover);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('form[action*="/comment/' . CirculationInterface::COMMENT_TARGET . '/' . $handover . '"]'));
        self::assertCount(1, $crawler->filter('form[action$="/handover/' . $handover . '/confirm"]'));
    }

    public function testTheAboutTabShowsWhatTheViewerHoldsAndWaitsFor(): void
    {
        // Arrange
        $this->reachAHandover();

        // Act
        $crawler = $this->as($this->first)->client->request('GET', '/en/circulation/' . self::TYPE . '?tab=about');

        // Assert
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Handovers under way', $crawler->text());
        self::assertStringContainsString('Waiting for you to confirm', $crawler->text());
    }

    public function testTheHandoverChatIsPrivateToItsTwoParticipants(): void
    {
        // Arrange
        $handover = $this->reachAHandover();
        $stranger = new Members(self::getContainer()->get(EntityManagerInterface::class))->member('Stranger');

        // Act
        $this->as($stranger)->post(
            '/en/comment/' . CirculationInterface::COMMENT_TARGET . '/' . $handover,
            'app_comment_create' . CirculationInterface::COMMENT_TARGET . $handover,
            [
                'content' => 'Butting in',
            ],
        );

        // Assert
        self::assertSame([], self::getContainer()->get(CommentService::class)->getFor(CirculationInterface::COMMENT_TARGET, $handover));
    }

    public function testAParticipantPostsInTheHandoverChat(): void
    {
        // Arrange
        $handover = $this->reachAHandover();

        // Act
        $this->as($this->first)->post(
            '/en/comment/' . CirculationInterface::COMMENT_TARGET . '/' . $handover,
            'app_comment_create' . CirculationInterface::COMMENT_TARGET . $handover,
            [
                'content' => 'See you on Thursday',
            ],
        );

        // Assert
        self::assertCount(1, self::getContainer()->get(CommentService::class)->getFor(CirculationInterface::COMMENT_TARGET, $handover));
    }

    public function testAWaitingMemberLeavesTheQueue(): void
    {
        // Arrange
        $this->reachAHandover();
        $this->requestAs($this->second);
        $request = $this->requestOf($this->second);
        self::assertSame(RequestStatus::Waiting, $request?->status);

        // Act
        $this->as($this->second)->post('/en/circulation/request/' . $request->ref . '/cancel', 'app_circulation_request_cancel' . $request->ref);

        // Assert
        self::assertSame(RequestStatus::Cancelled, $this->requestOf($this->second)?->status);
    }

    public function testOnlyAStewardCanRetireACopy(): void
    {
        // Arrange
        $this->donate();
        $copy = $this->copy();
        $steward = new Members(self::getContainer()->get(EntityManagerInterface::class))->admin('Steward');

        // Act
        $this->as($this->first)->post('/en/circulation/copy/' . $copy->ref . '/retire', 'app_circulation_copy_retire' . $copy->ref);
        $memberStatus = $this->client->getResponse()->getStatusCode();
        $statusAfterMember = $this->copy()->status;
        $this->as($steward)->post('/en/circulation/copy/' . $copy->ref . '/retire', 'app_circulation_copy_retire' . $copy->ref);

        // Assert
        self::assertSame(403, $memberStatus);
        self::assertSame(CopyStatus::Available, $statusAfterMember);
        self::assertSame(CopyStatus::Retired, $this->copy()->status);
    }

    public function testAStrangerCannotOpenTheHandoverPage(): void
    {
        // Arrange
        $handover = $this->reachAHandover();

        // Act
        $this->as($this->second)->client->request('GET', '/en/circulation/handover/' . $handover);

        // Assert
        self::assertResponseStatusCodeSame(404);
    }

    public function testCancellingAHandoverLeavesTheCopyWithTheGiverAndTheRequesterQueued(): void
    {
        // Arrange
        $handover = $this->reachAHandover();

        // Act
        $this->as($this->donor)->post('/en/circulation/handover/' . $handover . '/cancel', 'app_circulation_handover_cancel' . $handover);

        // Assert
        $copy = $this->copy();
        self::assertSame(CopyStatus::Available, $copy->status);
        self::assertSame($this->donor->getId(), $copy->holderUserId);
        self::assertSame(RequestStatus::Waiting, $this->requestOf($this->first)?->status);
    }

    public function testWithCirculationSwitchedOffTheDashboardIsGoneAndNothingCanBeDonated(): void
    {
        // Arrange
        self::getContainer()->get(ParticipationProvider::class)->enabled = false;

        // Act
        $this->as($this->donor)->client->request('GET', '/en/circulation/' . self::TYPE);
        $dashboard = $this->client->getResponse()->getStatusCode();
        $this->donate();

        // Assert
        self::assertSame(404, $dashboard);
        self::assertSame([], $this->shelf()->copies);
    }

    public function testDeletingTheItemRetiresItsCopiesAndClosesItsHandovers(): void
    {
        // Arrange
        $handover = $this->reachAHandover();

        // Act
        self::getContainer()->get(self::ITEM_ACTIONS)->dispatch(ItemAction::Deleted, self::TYPE, self::ITEM);

        // Assert
        self::assertSame(CopyStatus::Retired, $this->copy()->status);
        self::assertSame(HandoverStatus::Cancelled, $this->handover($handover)?->status);
    }

    private function reachAHandover(): int
    {
        $this->donate();
        $this->requestAs($this->first);

        return $this->openHandover($this->copy());
    }

    private function donate(): void
    {
        $this->as($this->donor)->post('/en/circulation/' . self::TYPE . '/' . self::ITEM . '/donate', 'app_circulation_donate' . self::TYPE . self::ITEM, [
            'label' => 'blue hardcover',
        ]);
    }

    private function requestAs(User $member): void
    {
        $this->as($member)->post('/en/circulation/' . self::TYPE . '/' . self::ITEM . '/request', 'app_circulation_request' . self::TYPE . self::ITEM);
    }

    private function as(User $member): self
    {
        $this->client->loginUser($member);

        return $this;
    }

    /**
     * @param array<string, string> $parameters
     */
    private function post(string $path, string $tokenId, array $parameters = []): void
    {
        $session = $this->client->getSession();
        $session->set('_csrf/' . $tokenId, 'primed-token');
        $session->save();

        $this->client->request('POST', $path, $parameters + ['_token' => 'primed-token']);
    }

    private function openHandover(PortableCopy $copy): int
    {
        $handovers = array_values(array_filter($this->shelf()->handovers, static fn(PortableHandover $handover): bool => $handover->copyRef === $copy->ref));
        self::assertNotSame([], $handovers);

        return $handovers[0]->ref;
    }

    private function copy(): PortableCopy
    {
        $copies = $this->shelf()->copies;
        self::assertCount(1, $copies);

        return $copies[0];
    }

    private function handover(int $ref): ?PortableHandover
    {
        return array_find($this->shelf()->handovers, static fn(PortableHandover $handover): bool => $handover->ref === $ref);
    }

    private function requestOf(User $member): ?PortableRequest
    {
        return array_find($this->shelf()->requests, static fn(PortableRequest $request): bool => $request->userId === $member->getId());
    }

    /**
     * @return list<PortableLedgerEntry>
     */
    private function ledgerOfType(LedgerEntryType $type): array
    {
        return array_values(array_filter($this->shelf()->ledger, static fn(PortableLedgerEntry $entry): bool => $entry->type === $type));
    }

    private function shelf(): PortableShelf
    {
        $circulation = self::getContainer()->get(CirculationInterface::class);

        return $circulation->export([$circulation->contextFor(self::TYPE)]);
    }
}
