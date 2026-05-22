<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security\Provider;

use App\Enum\SecurityEventType;
use App\Enum\SecurityRecommendation;
use App\Repository\AccessDeniedLogRepository;
use App\Service\Security\Provider\AccessDeniedProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AccessDeniedProviderTest extends TestCase
{
    public function testLenientBaseDoesNotBlockShortStreak(): void
    {
        // Arrange
        $provider = $this->buildProvider();

        // Act - 5 access-denied events stay in lenient zone
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

        // Act - 15 hits across 8+ distinct paths trips the script detector
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
        $reason = AccessDeniedProvider::resolveReason($exception, false);

        // Assert
        static::assertSame('csrf', $reason);
    }

    public function testResolveReasonClassifiesController(): void
    {
        // Arrange
        $exception = new AccessDeniedHttpException('forbidden');

        // Act
        $reason = AccessDeniedProvider::resolveReason($exception, true);

        // Assert
        static::assertSame('controller', $reason);
    }

    private function buildProvider(): AccessDeniedProvider
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $repo = $this->createStub(AccessDeniedLogRepository::class);
        $security = $this->createStub(Security::class);

        return new AccessDeniedProvider(new ArrayAdapter(), new NullLogger(), $em, $repo, $security);
    }
}
