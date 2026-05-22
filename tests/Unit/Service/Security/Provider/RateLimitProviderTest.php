<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security\Provider;

use App\Enum\SecurityEventType;
use App\Enum\SecurityRecommendation;
use App\Repository\RateLimitLogRepository;
use App\Service\Security\Provider\RateLimitProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
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

        // Act - 20 hits on support never block
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

        // Assert - by the tenth call, threat level is at 100
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
}
