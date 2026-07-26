<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\Member\CaptchaService;
use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CaptchaServiceTest extends TestCase
{
    private const string PROJECT_DIR = '/var/www/html';

    private MockObject|SessionInterface $sessionMock;
    private MockObject|RequestStack $requestStackMock;
    private CaptchaService $subject;

    protected function setUp(): void
    {
        $this->sessionMock = $this->createStub(SessionInterface::class);

        $this->requestStackMock = $this->createStub(RequestStack::class);
        $this->requestStackMock->method('getSession')->willReturn($this->sessionMock);

        $this->subject = new CaptchaService($this->requestStackMock, self::PROJECT_DIR);
    }

    public function testGenerateReturnsExistingImageFromSession(): void
    {
        // Arrange
        $expectedImage = 'base64_image_data';
        $this->sessionMock->method('get')->willReturn($expectedImage);

        // Act
        $result = $this->subject->generate();

        // Assert
        static::assertSame($expectedImage, $result);
    }

    public function testGenerateCreatesNewImageWhenNoneExists(): void
    {
        // Arrange
        $this->sessionMock = $this->createMock(SessionInterface::class);
        $this->sessionMock->method('get')->willReturn(null);

        $this->requestStackMock = $this->createStub(RequestStack::class);
        $this->requestStackMock->method('getSession')->willReturn($this->sessionMock);

        $this->subject = new CaptchaService($this->requestStackMock, self::PROJECT_DIR);

        // Assert
        $this->sessionMock
            ->expects($this->exactly(3))
            ->method('set')
            ->willReturnCallback(function (string $key, mixed $value) {
                match (true) {
                    str_contains($key, 'captcha_refresh') => $this->assertCount(1, $value),
                    str_contains($key, 'captcha_text') => $this->assertSame(4, strlen($value)),
                    str_contains($key, 'captcha_image') => $this->assertValidBase64Image($value),
                    default => $this->fail("Unexpected session key: {$key}"),
                };
            });

        // Act
        $this->subject->generate();
    }

    public function testIsValidReturnNullOnMatchingCode(): void
    {
        // Arrange
        $this->sessionMock = $this->createMock(SessionInterface::class);
        $this->requestStackMock = $this->createStub(RequestStack::class);
        $this->requestStackMock->method('getSession')->willReturn($this->sessionMock);
        $this->subject = new CaptchaService($this->requestStackMock, self::PROJECT_DIR);

        $this->sessionMock->method('get')->willReturn('hgfw');
        $this->sessionMock->expects($this->once())->method('remove')->with('captcha_attempts');

        // Act
        $result = $this->subject->isValid('hgfw');

        // Assert
        static::assertNull($result);
    }

    public function testIsValidReturnErrorOnMismatchedCode(): void
    {
        // Arrange
        $this->sessionMock = $this->createMock(SessionInterface::class);
        $this->requestStackMock = $this->createStub(RequestStack::class);
        $this->requestStackMock->method('getSession')->willReturn($this->sessionMock);
        $this->subject = new CaptchaService($this->requestStackMock, self::PROJECT_DIR);

        $this->sessionMock
            ->method('get')
            ->willReturnMap([
                ['captcha_text',     null, 'jrdf'],
                ['captcha_attempts', 0,    0],
            ]);
        $this->sessionMock->expects($this->once())->method('set')->with('captcha_attempts', 1);

        // Act
        $result = $this->subject->isValid('hgfw');

        // Assert
        static::assertSame('security.captcha_wrong', $result);
    }

    public function testIsValidForcesResetAfterMaxAttempts(): void
    {
        // Arrange
        $this->sessionMock = $this->createMock(SessionInterface::class);
        $this->requestStackMock = $this->createStub(RequestStack::class);
        $this->requestStackMock->method('getSession')->willReturn($this->sessionMock);
        $this->subject = new CaptchaService($this->requestStackMock, self::PROJECT_DIR);

        $this->sessionMock
            ->method('get')
            ->willReturnMap([
                ['captcha_text',     null, 'jrdf'],
                ['captcha_attempts', 0,    2], // 3rd attempt → triggers forced reset
            ]);

        // Assert
        $this->sessionMock->expects($this->exactly(3))->method('remove');

        // Act
        $result = $this->subject->isValid('hgfw');

        // Assert
        static::assertSame('security.captcha_wrong', $result);
    }

    public function testGetRefreshTimeReturnsZeroWhenNoRefreshHistory(): void
    {
        // Arrange
        $this->sessionMock = $this->createMock(SessionInterface::class);
        $this->requestStackMock = $this->createStub(RequestStack::class);
        $this->requestStackMock->method('getSession')->willReturn($this->sessionMock);
        $this->subject = new CaptchaService($this->requestStackMock, self::PROJECT_DIR);

        $this->sessionMock->expects($this->once())->method('get')->with('captcha_refresh')->willReturn([]);

        // Act
        $result = $this->subject->getRefreshTime();

        // Assert
        static::assertSame(0, $result);
    }

    public function testGetRefreshTimeReturnsSecondsUntilNextRefresh(): void
    {
        // Arrange
        $this->sessionMock = $this->createMock(SessionInterface::class);
        $this->requestStackMock = $this->createStub(RequestStack::class);
        $this->requestStackMock->method('getSession')->willReturn($this->sessionMock);
        $this->subject = new CaptchaService($this->requestStackMock, self::PROJECT_DIR);

        $this->sessionMock->expects($this->once())->method('get')->with('captcha_refresh')->willReturn([new DateTimeImmutable()]);

        // Act
        $result = $this->subject->getRefreshTime();

        // Assert
        static::assertGreaterThan(5, $result);
    }

    public function testGetRefreshTimeReturnsSmallestRemainingTime(): void
    {
        // Arrange
        $this->sessionMock = $this->createMock(SessionInterface::class);
        $this->requestStackMock = $this->createStub(RequestStack::class);
        $this->requestStackMock->method('getSession')->willReturn($this->sessionMock);
        $this->subject = new CaptchaService($this->requestStackMock, self::PROJECT_DIR);

        $refreshHistory = [
            new DateTimeImmutable('-10 seconds'),
            new DateTimeImmutable('-35 seconds'), // oldest - determines smallest remaining time
            new DateTimeImmutable('-20 seconds'),
        ];

        $this->sessionMock->expects($this->once())->method('get')->with('captcha_refresh')->willReturn($refreshHistory);

        // Act
        $result = $this->subject->getRefreshTime();

        // Assert
        static::assertLessThanOrEqual(25, $result);
    }

    #[DataProvider('refreshCountDataProvider')]
    public function testGetRefreshCount(array $refreshHistory, int $expectedCount): void
    {
        // Arrange
        $this->sessionMock = $this->createMock(SessionInterface::class);
        $this->requestStackMock = $this->createStub(RequestStack::class);
        $this->requestStackMock->method('getSession')->willReturn($this->sessionMock);
        $this->subject = new CaptchaService($this->requestStackMock, self::PROJECT_DIR);

        $this->sessionMock->expects($this->once())->method('get')->with('captcha_refresh')->willReturn($refreshHistory);
        $this->sessionMock->expects($this->once())->method('set')->with('captcha_refresh');

        // Act
        $result = $this->subject->getRefreshCount();

        // Assert
        static::assertSame($expectedCount, $result);
    }

    public static function refreshCountDataProvider(): Generator
    {
        yield 'single refresh' => [
            'refreshHistory' => [new DateTimeImmutable()],
            'expectedCount' => 1,
        ];
        yield 'multiple recent refreshes' => [
            'refreshHistory' => [
                new DateTimeImmutable(),
                new DateTimeImmutable(),
                new DateTimeImmutable(),
                new DateTimeImmutable(),
            ],
            'expectedCount' => 4,
        ];
        yield 'excludes expired refresh (older than 1 hour)' => [
            'refreshHistory' => [
                new DateTimeImmutable(),
                new DateTimeImmutable(),
                new DateTimeImmutable(),
                new DateTimeImmutable('-1 hour'),
            ],
            'expectedCount' => 3,
        ];
    }

    public function testResetDoesNotClearSessionWhenTooManyRefreshAttempts(): void
    {
        // Arrange
        $this->sessionMock = $this->createMock(SessionInterface::class);
        $this->requestStackMock = $this->createStub(RequestStack::class);
        $this->requestStackMock->method('getSession')->willReturn($this->sessionMock);
        $this->subject = new CaptchaService($this->requestStackMock, self::PROJECT_DIR);

        $refreshHistory = array_fill(0, 7, new DateTimeImmutable());

        $this->sessionMock->method('get')->willReturn($refreshHistory);

        // Assert
        $this->sessionMock->expects($this->never())->method('remove');

        // Act
        $this->subject->reset();
    }

    public function testResetClearsSessionWhenRefreshAttemptsWithinLimit(): void
    {
        // Arrange
        $this->sessionMock = $this->createMock(SessionInterface::class);
        $this->requestStackMock = $this->createStub(RequestStack::class);
        $this->requestStackMock->method('getSession')->willReturn($this->sessionMock);
        $this->subject = new CaptchaService($this->requestStackMock, self::PROJECT_DIR);

        $refreshHistory = [new DateTimeImmutable()];

        $this->sessionMock->method('get')->willReturn($refreshHistory);

        // Assert
        $this->sessionMock->expects($this->exactly(3))->method('remove');

        // Act
        $this->subject->reset();
    }

    private function assertValidBase64Image(mixed $value): void
    {
        $this->assertIsString($value);
        $this->assertGreaterThanOrEqual(200, strlen($value));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $value);
    }
}
