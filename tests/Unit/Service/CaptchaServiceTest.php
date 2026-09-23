<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\Security\CaptchaService;
use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class CaptchaServiceTest extends TestCase
{
    private const string PROJECT_DIR = __DIR__ . '/../../..';
    private const string FORM = 'app_register';

    private SessionInterface $session;
    private CaptchaService $subject;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());

        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getSession')->willReturn($this->session);

        $this->subject = new CaptchaService($requestStack, self::PROJECT_DIR);
    }

    public function testGenerateStoresCodeAndImageForTheForm(): void
    {
        // Act
        $image = $this->subject->generate(self::FORM);

        // Assert
        static::assertSame($image, $this->session->get('captcha_image_' . self::FORM));
        static::assertSame(4, strlen((string) $this->session->get('captcha_text_' . self::FORM)));
        static::assertGreaterThanOrEqual(200, strlen($image));
        static::assertCount(1, $this->session->get('captcha_refresh'));
    }

    public function testGenerateReturnsTheExistingImageForTheSameForm(): void
    {
        // Arrange
        $first = $this->subject->generate(self::FORM);

        // Act
        $second = $this->subject->generate(self::FORM);

        // Assert
        static::assertSame($first, $second);
        static::assertCount(1, $this->session->get('captcha_refresh'));
    }

    public function testIsValidAcceptsTheCodeCaseInsensitively(): void
    {
        // Arrange
        $this->subject->generate(self::FORM);
        $code = (string) $this->session->get('captcha_text_' . self::FORM);

        // Act
        $result = $this->subject->isValid(self::FORM, strtoupper($code));

        // Assert
        static::assertNull($result);
    }

    public function testIsValidConsumesTheChallengeOnSuccess(): void
    {
        // Arrange
        $this->subject->generate(self::FORM);
        $code = (string) $this->session->get('captcha_text_' . self::FORM);

        // Act
        $this->subject->isValid(self::FORM, $code);

        // Assert
        static::assertFalse($this->session->has('captcha_text_' . self::FORM));
        static::assertFalse($this->session->has('captcha_image_' . self::FORM));
        static::assertSame('security.captcha_wrong', $this->subject->isValid(self::FORM, $code));
    }

    public function testIsValidRejectsAnyAnswerWhenNoChallengeWasIssued(): void
    {
        // Act & Assert
        static::assertSame('security.captcha_wrong', $this->subject->isValid(self::FORM, ''));
        static::assertSame('security.captcha_wrong', $this->subject->isValid(self::FORM, 'abcd'));
    }

    public function testIsValidRejectsAnEmptyAnswerAfterTheChallengeWasBurned(): void
    {
        // Arrange
        $this->subject->generate(self::FORM);
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->subject->isValid(self::FORM, 'zzzz');
        }

        // Act
        $result = $this->subject->isValid(self::FORM, '');

        // Assert
        static::assertFalse($this->session->has('captcha_text_' . self::FORM));
        static::assertSame('security.captcha_wrong', $result);
    }

    public function testChallengesAreScopedPerForm(): void
    {
        // Arrange
        $this->subject->generate('app_register');
        $this->subject->generate('app_contact');
        $registerCode = (string) $this->session->get('captcha_text_app_register');

        // Act
        $wrongForm = $this->subject->isValid('app_contact', $registerCode);

        // Assert
        static::assertSame('security.captcha_wrong', $wrongForm);
        static::assertNull($this->subject->isValid('app_register', $registerCode));
    }

    public function testIsValidBurnsTheChallengeAfterThreeWrongAnswers(): void
    {
        // Arrange
        $this->subject->generate(self::FORM);

        // Act
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $result = $this->subject->isValid(self::FORM, 'zzzz');
        }

        // Assert
        static::assertSame('security.captcha_wrong', $result);
        static::assertFalse($this->session->has('captcha_text_' . self::FORM));
        static::assertFalse($this->session->has('captcha_attempts_' . self::FORM));
    }

    public function testResetClearsTheChallengeWithinTheRefreshLimit(): void
    {
        // Arrange
        $this->subject->generate(self::FORM);

        // Act
        $this->subject->reset(self::FORM);

        // Assert
        static::assertFalse($this->session->has('captcha_text_' . self::FORM));
    }

    public function testResetKeepsTheChallengeOnceTheRefreshLimitIsReached(): void
    {
        // Arrange
        $this->session->set('captcha_refresh', array_fill(0, 7, new DateTimeImmutable()));
        $this->session->set('captcha_text_' . self::FORM, 'abcd');

        // Act
        $this->subject->reset(self::FORM);

        // Assert
        static::assertSame('abcd', $this->session->get('captcha_text_' . self::FORM));
    }

    public function testGetRefreshTimeReturnsZeroWhenNoRefreshHistory(): void
    {
        // Act
        $result = $this->subject->getRefreshTime();

        // Assert
        static::assertSame(0, $result);
    }

    public function testGetRefreshTimeReturnsSmallestRemainingTime(): void
    {
        // Arrange
        $this->session->set('captcha_refresh', [
            new DateTimeImmutable('-10 seconds'),
            new DateTimeImmutable('-35 seconds'),
            new DateTimeImmutable('-20 seconds'),
        ]);

        // Act
        $result = $this->subject->getRefreshTime();

        // Assert
        static::assertLessThanOrEqual(25, $result);
        static::assertGreaterThan(0, $result);
    }

    public function testGetRefreshExpiriesListsTheLiveRefreshesAscendingAndPrunesTheRest(): void
    {
        // Arrange
        $this->session->set('captcha_refresh', [
            new DateTimeImmutable('-10 seconds'),
            new DateTimeImmutable('-2 minutes'),
            new DateTimeImmutable('-35 seconds'),
        ]);

        // Act
        $expiries = $this->subject->getRefreshExpiries();

        // Assert
        static::assertCount(2, $expiries);
        static::assertEqualsWithDelta(25, $expiries[0], 1);
        static::assertEqualsWithDelta(50, $expiries[1], 1);
        static::assertCount(2, $this->session->get('captcha_refresh'));
        static::assertSame(2, $this->subject->getRefreshCount());
        static::assertSame($expiries[0], $this->subject->getRefreshTime());
    }

    public function testGetRefreshExpiriesIsEmptyWithoutRefreshHistory(): void
    {
        // Act
        $expiries = $this->subject->getRefreshExpiries();

        // Assert
        static::assertSame([], $expiries);
    }

    #[DataProvider('refreshCountDataProvider')]
    public function testGetRefreshCount(array $refreshHistory, int $expectedCount): void
    {
        // Arrange
        $this->session->set('captcha_refresh', $refreshHistory);

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
            'refreshHistory' => array_fill(0, 4, new DateTimeImmutable()),
            'expectedCount' => 4,
        ];
        yield 'excludes a refresh older than a minute' => [
            'refreshHistory' => [
                new DateTimeImmutable(),
                new DateTimeImmutable(),
                new DateTimeImmutable(),
                new DateTimeImmutable('-1 hour'),
            ],
            'expectedCount' => 3,
        ];
    }
}
