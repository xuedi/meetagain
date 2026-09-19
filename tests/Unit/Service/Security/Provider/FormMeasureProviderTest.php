<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security\Provider;

use App\Enum\SecurityEventType;
use App\Enum\SecurityRecommendation;
use App\Repository\SecurityMeasureLogRepository;
use App\Service\Security\Provider\FormMeasureProvider;
use App\Service\Security\ProviderReport;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

class FormMeasureProviderTest extends TestCase
{
    public function testASpamBotIsBlockedOnItsFirstSubmission(): void
    {
        // Arrange
        $provider = $this->provider();

        // Act
        $report = $this->submit($provider, 'app_register', ['filled', 'too_fast']);

        // Assert
        static::assertSame(SecurityRecommendation::BlockSession, $report->recommendation);
        static::assertSame(100, $report->threatLevel);
    }

    public function testAForgedStampBlocksAlone(): void
    {
        // Arrange
        $provider = $this->provider();

        // Act
        $report = $this->submit($provider, 'app_contact', ['invalid_stamp']);

        // Assert
        static::assertSame(SecurityRecommendation::BlockSession, $report->recommendation);
    }

    public function testAHumanMistypingTheCaptchaThreeTimesStaysFarFromABlock(): void
    {
        // Arrange
        $provider = $this->provider();

        // Act
        $this->submit($provider, 'app_register', ['wrong_code']);
        $this->submit($provider, 'app_register', ['wrong_code']);
        $report = $this->submit($provider, 'app_register', ['wrong_code']);

        // Assert
        static::assertSame(SecurityRecommendation::Handled, $report->recommendation);
        static::assertSame(30, $report->threatLevel);
    }

    public function testTenWrongCaptchaCodesBlock(): void
    {
        // Arrange
        $provider = $this->provider();

        // Act
        $report = null;
        for ($i = 0; $i < 10; ++$i) {
            $report = $this->submit($provider, 'app_login', ['wrong_code']);
        }

        // Assert
        static::assertNotNull($report);
        static::assertSame(SecurityRecommendation::BlockSession, $report->recommendation);
    }

    public function testADuplicatedReasonInOneSubmissionCountsOnce(): void
    {
        // Arrange
        $provider = $this->provider();

        // Act
        $report = $this->submit($provider, 'app_register', ['missing_stamp', 'missing_stamp']);

        // Assert
        static::assertSame(60, $report->threatLevel);
    }

    public function testAnExpiredStampNeverScores(): void
    {
        // Arrange
        $provider = $this->provider();

        // Act
        $report = $this->submit($provider, 'app_register', ['expired_stamp']);

        // Assert
        static::assertSame(0, $report->threatLevel);
    }

    #[DataProvider('weightProvider')]
    public function testWeightsDependOnTheForm(string $reason, string $context, int $expected): void
    {
        // Act
        $weight = $this->provider()->weightFor($reason, $context);

        // Assert
        static::assertSame($expected, $weight);
    }

    public static function weightProvider(): Generator
    {
        yield 'too fast on registration' => ['too_fast', 'app_register', 40];
        yield 'too fast on login is softened for password managers' => ['too_fast', 'app_login', 15];
        yield 'honeypot on login keeps its weight' => ['filled', 'app_login', 60];
        yield 'unknown reason scores nothing' => ['something_else', 'app_register', 0];
    }

    public function testHandlesOnlyFormMeasureEvents(): void
    {
        // Arrange
        $provider = $this->provider();

        // Act + Assert
        static::assertTrue($provider->handles(SecurityEventType::FormMeasure));
        static::assertFalse($provider->handles(SecurityEventType::RateLimit));
        static::assertFalse($provider->handles(SecurityEventType::NotFound));
    }

    private function provider(): FormMeasureProvider
    {
        return new FormMeasureProvider(new ArrayAdapter(), new NullLogger(), $this->createStub(SecurityMeasureLogRepository::class));
    }

    /**
     * @param list<string> $reasons
     */
    private function submit(FormMeasureProvider $provider, string $context, array $reasons): ProviderReport
    {
        return $provider->observe(SecurityEventType::FormMeasure, new Request(), ['context' => $context, 'reasons' => $reasons], 'sess', '1.2.3.4');
    }
}
