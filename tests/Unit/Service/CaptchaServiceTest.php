<?php

declare(strict_types=1);

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
        // Arrange: set up session stub to return existing captcha image
        $expectedImage = 'base64_image_data';
        $this->sessionMock->method('get')->willReturn($expectedImage);

        // Act: generate captcha
        $result = $this->subject->generate();

        // Assert: returns existing image from session
        static::assertSame($expectedImage, $result);
    }

    public function testGenerateCreatesNewImageWhenNoneExists(): void
    {
        // Arrange: mock session to verify new captcha data is stored
        $this->sessionMock = $this->createMock(SessionInterface::class);
        $this->sessionMock->method('get')->willReturn(null);

        $this->requestStackMock = $this->createStub(RequestStack::class);
        $this->requestStackMock->method('getSession')->willReturn($this->sessionMock);

        $this->subject = new CaptchaService($this->requestStackMock, self::PROJECT_DIR);

        // Assert: verify session stores refresh timestamps, captcha text, and image
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

        // Act: generate new captcha
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
        $this->sessionMock
            ->expects($this->once())
            ->method('remove')
            ->with('captcha_attempts');

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
        $this->sessionMock
            ->expects($this->once())
            ->method('set')
            ->with('captcha_attempts', 1);

        // Act
        $result = $this->subject->isValid('hgfw');

        // Assert: generic message does not reveal the expected code
        static::assertSame('Wrong captcha code, please try again.', $result);
    }

    public function testIsValidForcesResetAfterMaxAttempts(): void
    {
        // Arrange: already at MAX_ATTEMPTS - 1 failed attempts
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

        // Assert: captcha is fully cleared after MAX_ATTEMPTS failures
        $this->sessionMock->expects($this->exactly(3))->method('remove');

        // Act
        $result = $this->subject->isValid('hgfw');

        // Assert
        static::assertSame('Wrong captcha code, please try again.', $result);
    }

    public function testGetRefreshTimeReturnsZeroWhenNoRefreshHistory(): void
    {
        // Arrange: session returns empty refresh history
        $this->sessionMock = $this->createMock(SessionInterface::class);
        $this->requestStackMock = $this->createStub(RequestStack::class);
        $this->requestStackMock->method('getSession')->willReturn($this->sessionMock);
        $this->subject = new CaptchaService($this->requestStackMock, self::PROJECT_DIR);

        $this->sessionMock
            ->expects($this->once())
            ->method('get')
            ->with('captcha_refresh')
            ->willReturn([]);

        // Act: get refresh time
        $result = $this->subject->getRefreshTime();

        // Assert: returns zero when no refresh history exists
        static::assertSame(0, $result);
    }

    public function testGetRefreshTimeReturnsSecondsUntilNextRefresh(): void
    {
        // Arrange: session returns single recent refresh timestamp
        $this->sessionMock = $this->createMock(SessionInterface::class);
        $this->requestStackMock = $this->createStub(RequestStack::class);
        $this->requestStackMock->method('getSession')->willReturn($this->sessionMock);
        $this->subject = new CaptchaService($this->requestStackMock, self::PROJECT_DIR);

        $this->sessionMock
            ->expects($this->once())
            ->method('get')
            ->with('captcha_refresh')
            ->willReturn([new DateTimeImmutable()]);

        // Act: get refresh time
        $result = $this->subject->getRefreshTime();

        // Assert: returns remaining seconds (with 5 second tolerance for test execution)
        static::assertGreaterThan(5, $result);
    }

    public function testGetRefreshTimeReturnsSmallestRemainingTime(): void
    {
        // Arrange: session returns multiple refresh timestamps, oldest determines wait time
        $this->sessionMock = $this->createMock(SessionInterface::class);
        $this->requestStackMock = $this->createStub(RequestStack::class);
        $this->requestStackMock->method('getSession')->willReturn($this->sessionMock);
        $this->subject = new CaptchaService($this->requestStackMock, self::PROJECT_DIR);

        $refreshHistory = [
            new DateTimeImmutable('-10 seconds'),
            new DateTimeImmutable('-35 seconds'), // oldest - determines smallest remaining time
            new DateTimeImmutable('-20 seconds'),
        ];

        $this->sessionMock
            ->expects($this->once())
            ->method('get')
            ->with('captcha_refresh')
            ->willReturn($refreshHistory);

        // Act: get refresh time
        $result = $this->subject->getRefreshTime();

        // Assert: returns time based on oldest timestamp (35 seconds ago = ~25 seconds remaining)
        static::assertLessThanOrEqual(25, $result);
    }

    #[DataProvider('refreshCountDataProvider')]
    public function testGetRefreshCount(array $refreshHistory, int $expectedCount): void
    {
        // Arrange: mock session to return refresh history and expect cleanup
        $this->sessionMock = $this->createMock(SessionInterface::class);
        $this->requestStackMock = $this->createStub(RequestStack::class);
        $this->requestStackMock->method('getSession')->willReturn($this->sessionMock);
        $this->subject = new CaptchaService($this->requestStackMock, self::PROJECT_DIR);

        $this->sessionMock
            ->expects($this->once())
            ->method('get')
            ->with('captcha_refresh')
            ->willReturn($refreshHistory);
        $this->sessionMock
            ->expects($this->once())
            ->method('set')
            ->with('captcha_refresh');

        // Act: get refresh count
        $result = $this->subject->getRefreshCount();

        // Assert: returns count of non-expired refresh attempts
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
        // Arrange: session has 7 refresh attempts (exceeds limit)
        $this->sessionMock = $this->createMock(SessionInterface::class);
        $this->requestStackMock = $this->createStub(RequestStack::class);
        $this->requestStackMock->method('getSession')->willReturn($this->sessionMock);
        $this->subject = new CaptchaService($this->requestStackMock, self::PROJECT_DIR);

        $refreshHistory = array_fill(0, 7, new DateTimeImmutable());

        $this->sessionMock->method('get')->willReturn($refreshHistory);

        // Assert: session remove should never be called when limit exceeded
        $this->sessionMock->expects($this->never())->method('remove');

        // Act: attempt reset
        $this->subject->reset();
    }

    public function testResetClearsSessionWhenRefreshAttemptsWithinLimit(): void
    {
        // Arrange: session has only 1 refresh attempt (within limit)
        $this->sessionMock = $this->createMock(SessionInterface::class);
        $this->requestStackMock = $this->createStub(RequestStack::class);
        $this->requestStackMock->method('getSession')->willReturn($this->sessionMock);
        $this->subject = new CaptchaService($this->requestStackMock, self::PROJECT_DIR);

        $refreshHistory = [new DateTimeImmutable()];

        $this->sessionMock->method('get')->willReturn($refreshHistory);

        // Assert: session should clear captcha text, image, and attempts
        $this->sessionMock->expects($this->exactly(3))->method('remove');

        // Act: reset captcha
        $this->subject->reset();
    }

    private function assertValidBase64Image(mixed $value): void
    {
        $this->assertIsString($value);
        $this->assertGreaterThanOrEqual(200, strlen($value));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $value);
    }
}
