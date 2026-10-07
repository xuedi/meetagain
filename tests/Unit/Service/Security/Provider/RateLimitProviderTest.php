<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security\Provider;

use App\Entity\RateLimitLog;
use App\Enum\SecurityEventType;
use App\Enum\SecurityRecommendation;
use App\Repository\RateLimitLogRepository;
use App\Service\Security\Provider\RateLimitProvider;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

class RateLimitProviderTest extends TestCase
{
    public function testLoginThrottlingBlocksImmediately(): void
    {
        // Arrange
        $em = $this->createStub(EntityManagerInterface::class);
        $repo = $this->createStub(RateLimitLogRepository::class);
        $provider = new RateLimitProvider(new ArrayAdapter(), new NullLogger(), $em, $repo);

        // Act
        $report = $provider->observe(SecurityEventType::RateLimit, new Request(), ['limiter' => 'login_throttling'], 'sess', '1.2.3.4');

        // Assert
        static::assertSame(SecurityRecommendation::Block, $report->recommendation);
        static::assertSame(100, $report->threatLevel);
    }

    public function testSupportLimiterIsLenient(): void
    {
        // Arrange
        $em = $this->createStub(EntityManagerInterface::class);
        $repo = $this->createStub(RateLimitLogRepository::class);
        $provider = new RateLimitProvider(new ArrayAdapter(), new NullLogger(), $em, $repo);

        // Act
        $report = null;
        for ($i = 0; $i < 20; ++$i) {
            $report = $provider->observe(SecurityEventType::RateLimit, new Request(), ['limiter' => 'support'], 'sess', '1.2.3.4');
        }

        // Assert
        static::assertNotNull($report);
        static::assertSame(SecurityRecommendation::Handled, $report->recommendation);
        static::assertLessThanOrEqual(60, $report->threatLevel);
    }

    public function testApiLimiterEscalates(): void
    {
        // Arrange
        $em = $this->createStub(EntityManagerInterface::class);
        $repo = $this->createStub(RateLimitLogRepository::class);
        $provider = new RateLimitProvider(new ArrayAdapter(), new NullLogger(), $em, $repo);

        // Act
        $reports = [];
        for ($i = 0; $i < 12; ++$i) {
            $reports[] = $provider->observe(SecurityEventType::RateLimit, new Request(), ['limiter' => 'api_default'], 'sess', '1.2.3.4');
        }

        // Assert
        static::assertSame(SecurityRecommendation::Block, end($reports)->recommendation);
    }

    public function testDoesNotHandleNonRateLimitEvents(): void
    {
        // Arrange
        $em = $this->createStub(EntityManagerInterface::class);
        $repo = $this->createStub(RateLimitLogRepository::class);
        $provider = new RateLimitProvider(new ArrayAdapter(), new NullLogger(), $em, $repo);

        // Act + Assert
        static::assertFalse($provider->handles(SecurityEventType::NotFound));
        static::assertFalse($provider->handles(SecurityEventType::AccessDenied));
        static::assertTrue($provider->handles(SecurityEventType::RateLimit));
    }

    #[DataProvider('userIdentifierProvider')]
    public function testTheLogRecordsTheTargetedAccountOnlyWhenKnown(array $context, ?string $expected): void
    {
        // Arrange
        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $provider = new RateLimitProvider(new ArrayAdapter(), new NullLogger(), $em, $this->createStub(RateLimitLogRepository::class));

        // Act
        $provider->observe(SecurityEventType::RateLimit, Request::create('/login'), $context, 'sess', '1.2.3.4');

        // Assert
        static::assertCount(1, $persisted);
        static::assertInstanceOf(RateLimitLog::class, $persisted[0]);
        static::assertSame('login_throttling', $persisted[0]->getLimiter());
        static::assertSame($expected, $persisted[0]->getUserIdentifier());
    }

    public static function userIdentifierProvider(): Generator
    {
        yield 'identifier given' => [['limiter' => 'login_throttling', 'userIdentifier' => 'jane@example.org'], 'jane@example.org'];
        yield 'empty identifier' => [['limiter' => 'login_throttling', 'userIdentifier' => ''], null];
        yield 'no identifier' => [['limiter' => 'login_throttling'], null];
    }

    public function testAFailingLogWriteStillReturnsTheReportAndWarns(): void
    {
        // Arrange
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willThrowException(new RuntimeException('db down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('warning')->with(static::stringContains('db down'));
        $provider = new RateLimitProvider(new ArrayAdapter(), $logger, $em, $this->createStub(RateLimitLogRepository::class));

        // Act
        $report = $provider->observe(SecurityEventType::RateLimit, new Request(), ['limiter' => 'login_throttling'], 'sess', '1.2.3.4');

        // Assert
        static::assertSame(SecurityRecommendation::Block, $report->recommendation);
    }

    public function testRetrospectiveScanGroupsRowsByLimiter(): void
    {
        // Arrange
        $provider = $this->retrospectiveProvider([$this->logRow('support'), $this->logRow('api_default'), $this->logRow('support')]);

        // Act
        $report = $provider->scanRetrospective(new DateTimeImmutable('-1 hour'), new DateTimeImmutable());

        // Assert
        static::assertSame(3, $report->threatLevel);
        static::assertSame(['rows' => 3, 'byLimiter' => ['support' => 2, 'api_default' => 1], 'loginThrottling' => false], $report->details);
    }

    public function testRetrospectiveLoginThrottlingAddsThirty(): void
    {
        // Arrange
        $provider = $this->retrospectiveProvider([$this->logRow('login_throttling'), $this->logRow('support')]);

        // Act
        $report = $provider->scanRetrospective(new DateTimeImmutable('-1 hour'), new DateTimeImmutable());

        // Assert
        static::assertSame(32, $report->threatLevel);
        static::assertTrue($report->details['loginThrottling']);
    }

    public function testRetrospectiveScanCapsAtABlock(): void
    {
        // Arrange
        $rows = [];
        for ($i = 0; $i < 90; ++$i) {
            $rows[] = $this->logRow('login_throttling');
        }
        $provider = $this->retrospectiveProvider($rows);

        // Act
        $report = $provider->scanRetrospective(new DateTimeImmutable('-1 hour'), new DateTimeImmutable());

        // Assert
        static::assertSame(100, $report->threatLevel);
        static::assertSame(SecurityRecommendation::Block, $report->recommendation);
    }

    /**
     * @param list<RateLimitLog> $rows
     */
    private function retrospectiveProvider(array $rows): RateLimitProvider
    {
        $repo = $this->createStub(RateLimitLogRepository::class);
        $repo->method('findFiltered')->willReturn($rows);

        return new RateLimitProvider(new ArrayAdapter(), new NullLogger(), $this->createStub(EntityManagerInterface::class), $repo);
    }

    private function logRow(string $limiter): RateLimitLog
    {
        return new RateLimitLog()
            ->setLimiter($limiter)
            ->setIp('1.2.3.4')
            ->setUrl('/x')
            ->setCreatedAt(new DateTimeImmutable());
    }
}
