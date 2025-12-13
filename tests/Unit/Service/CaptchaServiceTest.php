<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\CaptchaService;
use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CaptchaServiceTest extends TestCase
{
    private const string SESSION_ID = 'test_session_id';

    public function testGenerateReturnsExistingImageFromSession(): void
    {
        // Arrange: set up session stub to return existing captcha image
        $expectedImage = 'base64_image_data';

        $sessionStub = $this->createStub(SessionInterface::class);
        $sessionStub->method('getId')->willReturn(self::SESSION_ID);
        $sessionStub->method('get')->willReturn($expectedImage);

        $subject = new CaptchaService();
        $subject->setSession($sessionStub);

        // Act: generate captcha
        $result = $subject->generate();

        // Assert: returns existing image from session
        $this->assertSame($expectedImage, $result);
    }

    public function testGenerateCreatesNewImageWhenNoneExists(): void
    {
        // Arrange: mock session to verify new captcha data is stored
        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->method('getId')->willReturn(self::SESSION_ID);
        $sessionMock->method('get')->willReturn(null);

        // Assert: verify session stores refresh timestamps, captcha text, and image
        $sessionMock
            ->expects($this->exactly(3))
            ->method('set')
            ->willReturnCallback(function (string $key, mixed $value) {
                match (true) {
                    str_contains($key, 'captcha_refresh') => $this->assertCount(1, $value),
                    str_contains($key, 'captcha_text') => $this->assertSame(4, strlen($value)),
                    str_contains($key, 'captcha_image') => $this->assertValidBase64Image($value),
                    default => $this->fail("Unexpected session key: $key"),
                };
            });

        $subject = new CaptchaService();
        $subject->setSession($sessionMock);

        // Act: generate new captcha
        $subject->generate();
    }

    #[DataProvider('validationDataProvider')]
    public function testIsValid(string $storedCode, string $inputCode, ?string $expectedError): void
    {
        // Arrange: set up session mock to return stored captcha code
        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock
            ->expects($this->once())
            ->method('getId')
            ->willReturn(self::SESSION_ID);
        $sessionMock
            ->expects($this->once())
            ->method('get')
            ->with('captcha_text' . self::SESSION_ID)
            ->willReturn($storedCode);

        $subject = new CaptchaService();
        $subject->setSession($sessionMock);

        // Act: validate user input
        $result = $subject->isValid($inputCode);

        // Assert: returns null on success, error message on failure
        $this->assertSame($expectedError, $result);
    }

    public static function validationDataProvider(): Generator
    {
        yield 'matching code returns null' => [
            'storedCode' => 'hgfw',
            'inputCode' => 'hgfw',
            'expectedError' => null,
        ];
        yield 'mismatched code returns error message' => [
            'storedCode' => 'jrdf',
            'inputCode' => 'hgfw',
            'expectedError' => "Wrong captcha code, got 'hgfw' but expected 'jrdf'",
        ];
    }

    public function testGetRefreshTimeReturnsZeroWhenNoRefreshHistory(): void
    {
        // Arrange: session returns empty refresh history
        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->expects($this->once())->method('getId')->willReturn(self::SESSION_ID);
        $sessionMock
            ->expects($this->once())
            ->method('get')
            ->with('captcha_refresh' . self::SESSION_ID)
            ->willReturn([]);

        $subject = new CaptchaService();
        $subject->setSession($sessionMock);

        // Act: get refresh time
        $result = $subject->getRefreshTime();

        // Assert: returns zero when no refresh history exists
        $this->assertSame(0, $result);
    }

    public function testGetRefreshTimeReturnsSecondsUntilNextRefresh(): void
    {
        // Arrange: session returns single recent refresh timestamp
        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->expects($this->once())->method('getId')->willReturn(self::SESSION_ID);
        $sessionMock
            ->expects($this->once())
            ->method('get')
            ->with('captcha_refresh' . self::SESSION_ID)
            ->willReturn([new DateTimeImmutable()]);

        $subject = new CaptchaService();
        $subject->setSession($sessionMock);

        // Act: get refresh time
        $result = $subject->getRefreshTime();

        // Assert: returns remaining seconds (with 5 second tolerance for test execution)
        $this->assertGreaterThan(5, $result);
    }

    public function testGetRefreshTimeReturnsSmallestRemainingTime(): void
    {
        // Arrange: session returns multiple refresh timestamps, oldest determines wait time
        $refreshHistory = [
            new DateTimeImmutable('-10 seconds'),
            new DateTimeImmutable('-35 seconds'), // oldest - determines smallest remaining time
            new DateTimeImmutable('-20 seconds'),
        ];

        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->expects($this->once())->method('getId')->willReturn(self::SESSION_ID);
        $sessionMock
            ->expects($this->once())
            ->method('get')
            ->with('captcha_refresh' . self::SESSION_ID)
            ->willReturn($refreshHistory);

        $subject = new CaptchaService();
        $subject->setSession($sessionMock);

        // Act: get refresh time
        $result = $subject->getRefreshTime();

        // Assert: returns time based on oldest timestamp (35 seconds ago = ~25 seconds remaining)
        $this->assertLessThanOrEqual(25, $result);
    }

    #[DataProvider('refreshCountDataProvider')]
    public function testGetRefreshCount(array $refreshHistory, int $expectedCount): void
    {
        // Arrange: mock session to return refresh history and expect cleanup
        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->expects($this->exactly(2))->method('getId')->willReturn(self::SESSION_ID);
        $sessionMock
            ->expects($this->once())
            ->method('get')
            ->with('captcha_refresh' . self::SESSION_ID)
            ->willReturn($refreshHistory);
        $sessionMock
            ->expects($this->once())
            ->method('set')
            ->with('captcha_refresh' . self::SESSION_ID);

        $subject = new CaptchaService();
        $subject->setSession($sessionMock);

        // Act: get refresh count
        $result = $subject->getRefreshCount();

        // Assert: returns count of non-expired refresh attempts
        $this->assertSame($expectedCount, $result);
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
        // Arrange: session has 7 refresh attempts (exceeds limit)
        $refreshHistory = array_fill(0, 7, new DateTimeImmutable());

        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->method('get')->willReturn($refreshHistory);

        // Assert: session remove should never be called when limit exceeded
        $sessionMock->expects($this->never())->method('remove');

        $subject = new CaptchaService();
        $subject->setSession($sessionMock);

        // Act: attempt reset
        $subject->reset();
    }

    public function testResetClearsSessionWhenRefreshAttemptsWithinLimit(): void
    {
        // Arrange: session has only 1 refresh attempt (within limit)
        $refreshHistory = [new DateTimeImmutable()];

        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->method('get')->willReturn($refreshHistory);

        // Assert: session should clear captcha text and image
        $sessionMock->expects($this->exactly(2))->method('remove');

        $subject = new CaptchaService();
        $subject->setSession($sessionMock);

        // Act: reset captcha
        $subject->reset();
    }

    private function assertValidBase64Image(mixed $value): void
    {
        $this->assertIsString($value);
        $this->assertGreaterThanOrEqual(200, strlen($value));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $value);
    }
}
