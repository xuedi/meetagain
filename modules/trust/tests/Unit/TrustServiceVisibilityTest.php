<?php declare(strict_types=1);

namespace Module\Trust\Tests\Unit;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Trust\Contract\AccessProviderInterface;
use Module\Trust\Contract\ActionSourceInterface;
use Module\Trust\Contract\ContextDescriberInterface;
use Module\Trust\Contract\ContextDescriptor;
use Module\Trust\Contract\TrustAction;
use Module\Trust\Contract\ActionDescriptor;
use Module\Trust\Internal\AccessResolver;
use Module\Trust\Internal\ActionRegistry;
use Module\Trust\Internal\ConfigStore;
use Module\Trust\Internal\ContextRegistry;
use Module\Trust\Internal\Repository\TrustContextConfigRepository;
use Module\Trust\Internal\Repository\TrustGrantRepository;
use Module\Trust\Internal\ScoreCalculator;
use Module\Trust\Internal\ScoreProvider;
use Module\Trust\Internal\TrustService;
use Module\Trust\Internal\VouchService;
use Override;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class TrustServiceVisibilityTest extends TestCase
{
    private const int ADMINISTRATOR_ID = 1;
    private const int MEMBER_ID = 2;

    public function testAnAdministratorSeesTheWholeMap(): void
    {
        // Arrange
        $service = $this->service(self::ADMINISTRATOR_ID);

        // Act
        $scores = $service->getScores('ctx');

        // Assert
        self::assertSame([self::ADMINISTRATOR_ID, self::MEMBER_ID], array_keys($scores));
    }

    public function testAMemberSeesOnlyTheirOwnEntry(): void
    {
        // Arrange
        $service = $this->service(self::MEMBER_ID);

        // Act
        $scores = $service->getScores('ctx');

        // Assert
        self::assertSame([self::MEMBER_ID], array_keys($scores));
    }

    public function testAGuestSeesNothing(): void
    {
        // Arrange
        $service = $this->service(null);

        // Act
        $scores = $service->getScores('ctx');

        // Assert
        self::assertSame([], $scores);
    }

    private function service(?int $viewerId): TrustService
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(
            $viewerId === null ? null : $this->createConfiguredStub(User::class, ['getId' => $viewerId]),
        );
        $security->method('isGranted')->willReturn(false);

        $configRepository = $this->createStub(TrustContextConfigRepository::class);
        $configRepository->method('findByContext')->willReturn(null);
        $configStore = new ConfigStore($configRepository, $this->createStub(EntityManagerInterface::class));

        $grantRepository = $this->createStub(TrustGrantRepository::class);
        $grantRepository->method('findEdges')->willReturn([]);
        $grantRepository->method('findRevision')->willReturn(null);
        $grantRepository->method('countIncomingByUser')->willReturn([]);
        $grantRepository->method('findOutgoing')->willReturn([]);

        $registry = new ContextRegistry([new FixedContextDescriber()]);
        $scoreProvider = new ScoreProvider(
            [new FixedActionSource()],
            [],
            $registry,
            new ActionRegistry([new FixedActionSource()]),
            $configStore,
            new ScoreCalculator(new NullLogger()),
            $grantRepository,
            new ArrayAdapter(),
            new NullLogger(),
        );

        return new TrustService(
            $scoreProvider,
            $configStore,
            new VouchService($grantRepository, $this->createStub(EntityManagerInterface::class), $registry, $scoreProvider),
            new AccessResolver([new FixedAccessProvider(self::ADMINISTRATOR_ID)], $security),
        );
    }
}

final class FixedContextDescriber implements ContextDescriberInterface
{
    #[Override]
    public function describe(string $context): ?ContextDescriptor
    {
        return $context === 'ctx' ? new ContextDescriptor('ctx', 'Context') : null;
    }

    #[Override]
    public function describeAll(): iterable
    {
        yield new ContextDescriptor('ctx', 'Context');
    }
}

final class FixedAccessProvider implements AccessProviderInterface
{
    public function __construct(
        private readonly int $administratorId,
    ) {}

    #[Override]
    public function canView(string $context, int $userId): ?bool
    {
        return true;
    }

    #[Override]
    public function canAdminister(string $context, int $userId): ?bool
    {
        return $userId === $this->administratorId;
    }
}

final class FixedActionSource implements ActionSourceInterface
{
    #[Override]
    public function describeActions(string $context): iterable
    {
        yield new ActionDescriptor('handover', 'label', 5);
    }

    #[Override]
    public function replay(string $context): iterable
    {
        yield new TrustAction(1, 'handover', new DateTimeImmutable('2026-01-01'));
        yield new TrustAction(2, 'handover', new DateTimeImmutable('2026-01-02'));
    }

    #[Override]
    public function getRevision(string $context): ?string
    {
        return 'fixed';
    }
}
