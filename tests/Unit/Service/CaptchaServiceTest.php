<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\CaptchaService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CaptchaServiceTest extends TestCase
{
    private MockObject|SessionInterface $sessionMock;
    private CaptchaService $subject;

    protected function setUp(): void
    {
        $this->sessionMock = $this->createMock(SessionInterface::class);

        $this->subject = new CaptchaService();
        $this->subject->setSession(
            session: $this->sessionMock
        );
    }

    public function testGenerateIsReturningExistingData(): void
    {
        $expected = 'base64_image_data';
        $sessionId = 'test_session_id';

        $this->sessionMock
            ->method('getId')
            ->willReturn($sessionId);

        $this->sessionMock
            ->method('get')
            ->with('captcha_image' . $sessionId)
            ->willReturn($expected);

        $result = $this->subject->generate();
        $this->assertEquals($expected, $result);
    }

    public function testGenerateNewImage(): void
    {
        $sessionId = 'test_session_id';

        $this->sessionMock
            ->method('getId')
            ->willReturn($sessionId);

        $this->sessionMock
            ->method('get')
            ->willReturn(null);

        $this->sessionMock
            ->expects($this->exactly(3))
            ->method('set')
            ->willReturnCallback(function (string $key, mixed $value) use ($sessionId) {
                switch ($key) {
                    case 'captcha_refresh' . $sessionId:
                        $this->assertCount(1, $value);
                        break;
                    case 'captcha_text' . $sessionId:
                        $this->assertTrue(strlen($value) == 4);
                        break;
                    case 'captcha_image' . $sessionId:
                        $this->assertTrue(strlen($value) >= 200);
                        $this->assertBase64($value);
                        break;
                }
            });

        $this->subject->generate();
    }

    #[DataProvider('getIsValidMatrix')]
    public function testIsValid(string $sessionReturn, string $code, mixed $expected): void
    {
        $sessionId = 'test_session_id';

        $this->sessionMock
            ->expects($this->once())
            ->method('getId')
            ->willReturn($sessionId);

        $this->sessionMock
            ->expects($this->once())
            ->method('get')
            ->with('captcha_text' . $sessionId)
            ->willReturn($sessionReturn);

        $result = $this->subject->isValid($code);
        $this->assertEquals($expected, $result);
    }

    public static function getIsValidMatrix(): array
    {
        return [
            [
                'sessionReturn' => 'hgfw',
                'code' => 'hgfw',
                'expected' => null
            ],
            [
                'sessionReturn' => 'jrdf',
                'code' => 'hgfw',
                'expected' => "Wrong captcha code, got 'hgfw' but expected 'jrdf'"
            ],
        ];
    }

    public function testGetRefreshTimeOnEmpty(): void
    {
        $sessionId = 'test_session_id';
        $sessionReturn = [];
        $expected = 0;

        $this->sessionMock
            ->expects($this->once())
            ->method('getId')
            ->willReturn($sessionId);

        $this->sessionMock
            ->expects($this->once())
            ->method('get')
            ->with('captcha_refresh' . $sessionId)
            ->willReturn($sessionReturn);

        $result = $this->subject->getRefreshTime();
        $this->assertEquals($expected, $result);
    }

    public function testGetRefreshTimSingle(): void
    {
        $sessionId = 'test_session_id';
        $sessionReturn = [
            new DateTimeImmutable(),
        ];
        $expectedGreaterThan = 5; // give phpunit 5 seconds to execute the test

        $this->sessionMock
            ->expects($this->once())
            ->method('getId')
            ->willReturn($sessionId);

        $this->sessionMock
            ->expects($this->once())
            ->method('get')
            ->with('captcha_refresh' . $sessionId)
            ->willReturn($sessionReturn);

        $result = $this->subject->getRefreshTime();
        $this->assertGreaterThan($expectedGreaterThan, $result);
    }

    public function testGetRefreshTimGetSmallest(): void
    {
        $sessionId = 'test_session_id';
        $sessionReturn = [
            new DateTimeImmutable('-10 seconds'),
            new DateTimeImmutable('-35 seconds'), // expected, 25 or smaller
            new DateTimeImmutable('-20 seconds'),
        ];
        $expectedGreaterThan = 25;

        $this->sessionMock
            ->expects($this->once())
            ->method('getId')
            ->willReturn($sessionId);

        $this->sessionMock
            ->expects($this->once())
            ->method('get')
            ->with('captcha_refresh' . $sessionId)
            ->willReturn($sessionReturn);

        $result = $this->subject->getRefreshTime();
        $this->assertLessThanOrEqual($expectedGreaterThan, $result);
    }

    #[DataProvider('getRefreshCountMatrix')]
    public function testGetRefreshCount(array $sessionReturn, int $expected): void
    {
        $sessionId = 'test_session_id';

        $this->sessionMock
            ->expects($this->exactly(2))
            ->method('getId')
            ->willReturn($sessionId);

        $this->sessionMock
            ->expects($this->once())
            ->method('set')
            ->with('captcha_refresh' . $sessionId);

        $this->sessionMock
            ->expects($this->once())
            ->method('get')
            ->with('captcha_refresh' . $sessionId)
            ->willReturn($sessionReturn);

        $result = $this->subject->getRefreshCount();
        $this->assertEquals($expected, $result);
    }

    public static function getRefreshCountMatrix(): array
    {
        return [
            [
                'sessionReturn' => [
                    new DateTimeImmutable(),
                ],
                'expected' => 1
            ],
            [
                'sessionReturn' => [
                    new DateTimeImmutable(),
                    new DateTimeImmutable(),
                    new DateTimeImmutable(),
                    new DateTimeImmutable(),
                ],
                'expected' => 4
            ],
            [
                'sessionReturn' => [
                    new DateTimeImmutable(),
                    new DateTimeImmutable(),
                    new DateTimeImmutable(),
                    new DateTimeImmutable('-1 hour'), // is timed out
                ],
                'expected' => 3
            ],
        ];
    }

    public function testResetFails(): void
    {
        $sessionReturn = [
            new DateTimeImmutable(),
            new DateTimeImmutable(),
            new DateTimeImmutable(),
            new DateTimeImmutable(),
            new DateTimeImmutable(),
            new DateTimeImmutable(),
            new DateTimeImmutable(),
        ];

        $this->sessionMock
            ->expects($this->never())
            ->method('remove');

        $this->sessionMock
            ->method('get')
            ->willReturn($sessionReturn);

        $this->subject->reset();
    }

    public function testResetSucceed(): void
    {
        $sessionReturn = [
            new DateTimeImmutable(),
        ];

        $this->sessionMock
            ->expects($this->exactly(2))
            ->method('remove');

        $this->sessionMock
            ->method('get')
            ->willReturn($sessionReturn);

        $this->subject->reset();
    }

    private function assertBase64(mixed $value): void
    {
        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $value)) {
            $this->fail('Value is not base64 encoded');
        }
    }
}
