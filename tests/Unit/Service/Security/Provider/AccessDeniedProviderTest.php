<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security\Provider;

use App\Entity\AccessDeniedLog;
use App\Enum\SecurityEventType;
use App\Enum\SecurityRecommendation;
use App\Repository\AccessDeniedLogRepository;
use App\Service\Security\Provider\AccessDeniedProvider;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class AccessDeniedProviderTest extends TestCase
{
    public function testLenientBaseDoesNotBlockShortStreak(): void
    {
        // Arrange
        $provider = $this->buildProvider();

        // Act
        $report = null;
        for ($i = 0; $i < 5; ++$i) {
            $report = $provider->observe(SecurityEventType::AccessDenied, Request::create('/page-' . $i), ['reason' => 'voter'], 'sess', '1.2.3.4');
        }

        // Assert
        static::assertNotNull($report);
        static::assertSame(SecurityRecommendation::Handled, $report->recommendation);
    }

    public function testDistinctPathScriptDetectionJumpsToBlock(): void
    {
        // Arrange
        $provider = $this->buildProvider();

        // Act
        $report = null;
        for ($i = 0; $i < 15; ++$i) {
            $report = $provider->observe(SecurityEventType::AccessDenied, Request::create('/path-' . $i), ['reason' => 'voter'], 'sess', '1.2.3.4');
        }

        // Assert
        static::assertNotNull($report);
        static::assertSame(SecurityRecommendation::Block, $report->recommendation);
    }

    public function testCsrfReasonBoostsThreatLevel(): void
    {
        // Arrange
        $provider = $this->buildProvider();

        // Act
        $reportNoCsrf = $provider->observe(SecurityEventType::AccessDenied, Request::create('/x'), ['reason' => 'voter'], 'sess-a', '1.1.1.1');
        $reportWithCsrf = $provider->observe(SecurityEventType::AccessDenied, Request::create('/x'), ['reason' => 'csrf'], 'sess-b', '2.2.2.2');

        // Assert
        static::assertGreaterThan($reportNoCsrf->threatLevel, $reportWithCsrf->threatLevel);
    }

    public function testResolveReasonClassifiesCsrfFromMessage(): void
    {
        // Arrange
        $exception = new RuntimeException('Invalid CSRF token');

        // Act
        $reason = $this->buildProvider()->resolveReason($exception, false);

        // Assert
        static::assertSame('csrf', $reason);
    }

    public function testResolveReasonClassifiesController(): void
    {
        // Arrange
        $exception = new AccessDeniedHttpException('forbidden');

        // Act
        $reason = $this->buildProvider()->resolveReason($exception, true);

        // Assert
        static::assertSame('controller', $reason);
    }

    #[DataProvider('voterOrFirewallProvider')]
    public function testResolveReasonTellsVoterFromFirewall(Throwable $exception, string $expected): void
    {
        // Act
        $reason = $this->buildProvider()->resolveReason($exception, false);

        // Assert
        static::assertSame($expected, $reason);
    }

    public static function voterOrFirewallProvider(): Generator
    {
        yield 'voter named by the wrapped exception' => [new RuntimeException('denied', 0, new LogicException('rejected by voter')), 'voter'];
        yield 'voter named by the message' => [new RuntimeException('Access Denied by #[IsGranted]'), 'voter'];
        yield 'previous without a voter falls back to firewall' => [new RuntimeException('denied', 0, new LogicException('other')), 'firewall'];
        yield 'plain denial is the firewall' => [new RuntimeException('Full authentication is required'), 'firewall'];
    }

    public function testAFailingLogWriteStillReturnsTheReportAndWarns(): void
    {
        // Arrange
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willThrowException(new RuntimeException('db down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('warning')->with(static::stringContains('db down'));
        $provider = $this->buildProvider(em: $em, logger: $logger);

        // Act
        $report = $provider->observe(SecurityEventType::AccessDenied, Request::create('/admin'), ['reason' => 'voter'], 'sess', '1.2.3.4');

        // Assert
        static::assertSame(1, $report->details['hits']);
    }

    public function testRetrospectiveScanGroupsRowsByReason(): void
    {
        // Arrange
        $provider = $this->buildProvider(rows: [$this->logRow('voter'), $this->logRow('csrf'), $this->logRow('voter')]);

        // Act
        $report = $provider->scanRetrospective(new DateTimeImmutable('-1 hour'), new DateTimeImmutable());

        // Assert
        static::assertSame(3, $report->threatLevel);
        static::assertSame(['rows' => 3, 'byReason' => ['voter' => 2, 'csrf' => 1]], $report->details);
        static::assertSame(SecurityRecommendation::Handled, $report->recommendation);
    }

    public function testRetrospectiveScanBlocksAtOneHundredRows(): void
    {
        // Arrange
        $rows = [];
        for ($i = 0; $i < 150; ++$i) {
            $rows[] = $this->logRow('firewall');
        }
        $provider = $this->buildProvider(rows: $rows);

        // Act
        $report = $provider->scanRetrospective(new DateTimeImmutable('-1 hour'), new DateTimeImmutable());

        // Assert
        static::assertSame(100, $report->threatLevel);
        static::assertSame(SecurityRecommendation::Block, $report->recommendation);
    }

    /**
     * @param list<AccessDeniedLog> $rows
     */
    private function buildProvider(?EntityManagerInterface $em = null, ?LoggerInterface $logger = null, array $rows = []): AccessDeniedProvider
    {
        $repo = $this->createStub(AccessDeniedLogRepository::class);
        $repo->method('findFiltered')->willReturn($rows);
        $security = $this->createStub(Security::class);

        return new AccessDeniedProvider(
            new ArrayAdapter(),
            $logger ?? new NullLogger(),
            $em ?? $this->createStub(EntityManagerInterface::class),
            $repo,
            $security,
        );
    }

    private function logRow(string $reason): AccessDeniedLog
    {
        return new AccessDeniedLog()
            ->setReason($reason)
            ->setIp('1.2.3.4')
            ->setUrl('/x')
            ->setCreatedAt(new DateTimeImmutable());
    }
}
