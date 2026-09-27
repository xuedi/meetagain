<?php declare(strict_types=1);

namespace Module\Trust\Tests\Unit\Internal;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Trust\Contract\TrustAction;
use Module\Trust\Internal\AccessResolver;
use Module\Trust\Internal\ContextRegistry;
use Module\Trust\Internal\TrustService;
use Module\Trust\Internal\VouchService;
use Module\Trust\Tests\Stub\AccessProvider;
use Module\Trust\Tests\Stub\ActionSource;
use Module\Trust\Tests\Stub\ContextDescriber;
use Module\Trust\Tests\Stub\ScoreProviders;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class TrustServiceTest extends TestCase
{
    use ScoreProviders;

    private const int ADMINISTRATOR_ID = 1;
    private const int MEMBER_ID = 2;

    public function testAnAdministratorSeesTheWholeMap(): void
    {
        // Arrange
        $service = $this->service(self::ADMINISTRATOR_ID);

        // Act
        $scores = $service->getScores(ContextDescriber::CONTEXT);

        // Assert
        self::assertSame([self::ADMINISTRATOR_ID, self::MEMBER_ID], array_keys($scores));
    }

    public function testAMemberSeesOnlyTheirOwnEntry(): void
    {
        // Arrange
        $service = $this->service(self::MEMBER_ID);

        // Act
        $scores = $service->getScores(ContextDescriber::CONTEXT);

        // Assert
        self::assertSame([self::MEMBER_ID], array_keys($scores));
    }

    public function testAGuestSeesNothing(): void
    {
        // Arrange
        $service = $this->service(null);

        // Act
        $scores = $service->getScores(ContextDescriber::CONTEXT);

        // Assert
        self::assertSame([], $scores);
    }

    private function service(?int $viewerId): TrustService
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($viewerId === null ? null : $this->createConfiguredStub(User::class, ['getId' => $viewerId]));
        $security->method('isGranted')->willReturn(false);

        $source = new ActionSource();
        $source->actions = [
            new TrustAction(self::ADMINISTRATOR_ID, ActionSource::HANDOVER, new DateTimeImmutable('2026-01-01')),
            new TrustAction(self::MEMBER_ID, ActionSource::HANDOVER, new DateTimeImmutable('2026-01-02')),
        ];
        $access = new AccessProvider();
        $access->administratorId = self::ADMINISTRATOR_ID;
        $configStore = $this->configStore();
        $grants = $this->grantsWithoutEdges();
        $scoreProvider = $this->scoreProvider([$source], $grants, configStore: $configStore);

        return new TrustService(
            $scoreProvider,
            $configStore,
            new VouchService($grants, $this->createStub(EntityManagerInterface::class), new ContextRegistry([new ContextDescriber()]), $scoreProvider),
            new AccessResolver([$access], $security),
        );
    }
}
