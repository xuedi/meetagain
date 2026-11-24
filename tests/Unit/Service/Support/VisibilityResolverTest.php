<?php declare(strict_types=1);

namespace Tests\Unit\Service\Support;

use App\Entity\SupportRequest;
use App\Entity\User;
use App\Enum\SupportAudience;
use App\Enum\SupportRequestStatus;
use App\Filter\Admin\Support\AdminSupportListFilterInterface;
use App\Filter\Admin\Support\AdminSupportListFilterService;
use App\Repository\SupportRequestRepository;
use App\Service\Support\RecipientResolver;
use App\Service\Support\VisibilityResolver;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bundle\SecurityBundle\Security;

class VisibilityResolverTest extends TestCase
{
    public function testAnAdminSeesEveryAudience(): void
    {
        // Arrange
        $resolver = $this->makeResolver(isAdmin: true, filters: []);

        // Act & Assert
        static::assertTrue($resolver->canView($this->request(SupportAudience::Admin)));
        static::assertTrue($resolver->canView($this->request(SupportAudience::Organizer)));
    }

    public function testAStewardNeverSeesWhatWasAddressedToTheAdmins(): void
    {
        // Arrange
        $resolver = $this->makeResolver(isAdmin: false, filters: []);

        // Act & Assert
        static::assertTrue($resolver->canView($this->request(SupportAudience::Organizer)));
        static::assertFalse($resolver->canView($this->request(SupportAudience::Admin)));
    }

    public function testTheFilterChainNarrowsWhatAStewardCanView(): void
    {
        // Arrange
        $resolver = $this->makeResolver(isAdmin: false, filters: [$this->filter([1, 2, 3]), $this->filter([3, 4])]);

        // Act & Assert
        static::assertTrue($resolver->canView($this->request(SupportAudience::Organizer, id: 3)));
        static::assertFalse($resolver->canView($this->request(SupportAudience::Organizer, id: 1)));
    }

    public function testABlockingFilterHidesEverything(): void
    {
        // Arrange
        $resolver = $this->makeResolver(isAdmin: false, filters: [$this->filter([])]);

        // Act & Assert
        static::assertFalse($resolver->canView($this->request(SupportAudience::Organizer, id: 7)));
        static::assertSame([], $resolver->getVisibleRequests());
        static::assertSame(['involved' => 0, 'elsewhere' => 0], $resolver->countWaiting($this->user(1)));
    }

    public function testAFilterWithNoOpinionLeavesTheSetUnrestricted(): void
    {
        // Arrange
        $resolver = $this->makeResolver(isAdmin: false, filters: [$this->filter(null)]);

        // Act & Assert
        static::assertTrue($resolver->canView($this->request(SupportAudience::Organizer, id: 99)));
    }

    /**
     * @param list<int> $recipientIds
     * @param array{involved: int, elsewhere: int} $expected
     */
    #[DataProvider('provideAnAdminsWaitingCountCases')]
    public function testAnAdminCountsOnlyTheRequestsTheyAreInvolvedInAsWaitingOnThem(
        SupportAudience $audience,
        SupportRequestStatus $status,
        bool $invited,
        array $recipientIds,
        array $expected,
    ): void {
        // Arrange
        $request = new SupportRequest()
            ->setAudience($audience)
            ->setStatus($status);
        new ReflectionProperty(SupportRequest::class, 'id')->setValue($request, 10);
        if ($invited) {
            $request->setInvitedAdminsAt(new DateTimeImmutable());
        }
        $resolver = $this->makeResolver(isAdmin: true, filters: [], open: [$request], recipientIds: $recipientIds);

        // Act
        $counts = $resolver->countWaiting($this->user(1));

        // Assert
        static::assertSame($expected, $counts);
    }

    public static function provideAnAdminsWaitingCountCases(): iterable
    {
        $organizer = SupportAudience::Organizer;
        yield 'uninvited organizer request is info' => [$organizer, SupportRequestStatus::New, false, [2], ['involved' => 0, 'elsewhere' => 1]];
        yield 'it stays info once the organizer read it' => [$organizer, SupportRequestStatus::Read, false, [2], ['involved' => 0, 'elsewhere' => 1]];
        yield 'a reopened one is info again' => [$organizer, SupportRequestStatus::Reopened, false, [2], ['involved' => 0, 'elsewhere' => 1]];
        yield 'it clears once the organizer answered' => [$organizer, SupportRequestStatus::Replied, false, [2], ['involved' => 0, 'elsewhere' => 0]];
        yield 'after an invitation it is action' => [$organizer, SupportRequestStatus::New, true, [2], ['involved' => 1, 'elsewhere' => 0]];
        yield 'an admin among the recipients gets action' => [$organizer, SupportRequestStatus::New, false, [2, 1], ['involved' => 1, 'elsewhere' => 0]];
        yield 'a read request the admin is involved in counts nowhere' => [
            $organizer,
            SupportRequestStatus::Read,
            false,
            [1],
            ['involved' => 0, 'elsewhere' => 0],
        ];
        yield 'an admin audience request is action' => [SupportAudience::Admin, SupportRequestStatus::New, false, [], ['involved' => 1, 'elsewhere' => 0]];
    }

    public function testAStewardCountsEveryNewRequestTheySeeAsAction(): void
    {
        // Arrange
        $resolver = $this->makeResolver(isAdmin: false, filters: [$this->filter([1, 2])]);

        // Act
        $counts = $resolver->countWaiting($this->user(7));

        // Assert
        static::assertSame(['involved' => 5, 'elsewhere' => 0], $counts);
    }

    /**
     * @param array<AdminSupportListFilterInterface> $filters
     * @param list<SupportRequest> $open
     * @param list<int> $recipientIds
     */
    private function makeResolver(bool $isAdmin, array $filters, array $open = [], array $recipientIds = []): VisibilityResolver
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($isAdmin);

        $repository = $this->createStub(SupportRequestRepository::class);
        $repository->method('findForAdminList')->willReturn([]);
        $repository->method('countNew')->willReturn(5);
        $repository
            ->method('findOpen')
            ->willReturn(array_values(array_filter($open, static fn(SupportRequest $request) => in_array(
                $request->getStatus(),
                SupportRequestStatus::open(),
                true,
            ))));

        $recipients = $this->createStub(RecipientResolver::class);
        $recipients
            ->method('resolveMany')
            ->willReturnCallback(fn(array $requests): array => array_fill_keys(
                array_map(static fn(SupportRequest $request): ?int => $request->getId(), $requests),
                array_map($this->user(...), $recipientIds),
            ));

        return new VisibilityResolver(new AdminSupportListFilterService($filters), $repository, $security, $recipients);
    }

    private function user(int $id): User
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn($id);

        return $user;
    }

    /** @param array<int>|null $ids */
    private function filter(?array $ids): AdminSupportListFilterInterface
    {
        $filter = $this->createStub(AdminSupportListFilterInterface::class);
        $filter->method('getRequestIdFilter')->willReturn($ids);

        return $filter;
    }

    private function request(SupportAudience $audience, int $id = 1): SupportRequest
    {
        $request = $this->createStub(SupportRequest::class);
        $request->method('getAudience')->willReturn($audience);
        $request->method('getId')->willReturn($id);

        return $request;
    }
}
