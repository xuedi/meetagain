<?php declare(strict_types=1);

namespace Tests\Unit\Form;

use App\Enum\SecurityEventType;
use App\Enum\SecurityMeasure;
use App\Form\HumanCheckType;
use App\Form\PasswordResetType;
use App\Form\RegistrationType;
use App\Form\SupportRequestType;
use App\Service\Security\CaptchaService;
use App\Service\Security\ChallengeSigner;
use App\Service\Security\MeasureLogger;
use App\Service\Security\MeasureSettings;
use App\Service\Security\SecurityService;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

final class HumanCheckTypeTest extends TestCase
{
    private const string CONTEXT = 'app_register';
    private const string CAPTCHA_IMAGE = 'base64-captcha-image';

    private MockClock $clock;
    private ChallengeSigner $signer;
    /** @var list<array{0: SecurityMeasure, 1: string}> */
    private array $blocks = [];
    /** @var list<SecurityMeasure> */
    private array $passes = [];
    /** @var list<array{0: SecurityEventType, 1: array<string, mixed>}> */
    private array $events = [];
    private Request $request;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-09-18 10:00:00');
        $this->signer = new ChallengeSigner('a-test-secret', new ArrayAdapter(), $this->clock);
        $this->blocks = [];
        $this->passes = [];
        $this->events = [];
        $this->request = Request::create('/register', 'POST');
    }

    #[DataProvider('guestFormProvider')]
    public function testEveryGuestFormCarriesTheHumanCheck(string $type, array $options): void
    {
        // Act
        $form = $this->factory()->create($type, null, $options);

        // Assert
        static::assertTrue($form->has('meta'), $type . ' must include the human check');
    }

    public static function guestFormProvider(): Generator
    {
        yield 'registration' => [RegistrationType::class, []];
        yield 'password reset' => [PasswordResetType::class, []];
        yield 'guest support request' => [SupportRequestType::class, ['guest' => true]];
    }

    public function testAMemberSupportRequestCarriesNoHumanCheck(): void
    {
        // Act
        $form = $this->factory()->create(SupportRequestType::class, null, ['guest' => false]);

        // Assert
        static::assertFalse($form->has('meta'));
    }

    public function testOnlyTheEnabledMeasuresGetAField(): void
    {
        // Act
        $all = $this->humanCheck(SecurityMeasure::cases());
        $honeypotOnly = $this->humanCheck([SecurityMeasure::Honeypot]);

        // Assert
        static::assertTrue($all->has('captcha'));
        static::assertTrue($all->has(HumanCheckType::HONEYPOT_FIELD));
        static::assertTrue($all->has('stamp'));
        static::assertTrue($all->has('proof'));

        static::assertFalse($honeypotOnly->has('captcha'));
        static::assertTrue($honeypotOnly->has(HumanCheckType::HONEYPOT_FIELD));
        static::assertFalse($honeypotOnly->has('stamp'));
        static::assertFalse($honeypotOnly->has('proof'));
    }

    public function testTheRenderedViewCarriesAFreshStampAndTheCaptchaImage(): void
    {
        // Act
        $view = $this->humanCheck([SecurityMeasure::ImageCaptcha, SecurityMeasure::ProofOfWork])->createView();

        // Assert
        static::assertNotSame('', $view->children['stamp']->vars['value']);
        static::assertNotNull($this->signer->verify($view->children['stamp']->vars['value'], self::CONTEXT));
        static::assertSame(self::CAPTCHA_IMAGE, $view->vars['captchaImage']);
        static::assertTrue($view->vars['powEnabled']);
    }

    public function testRenderingTheFormNeverRotatesTheCaptcha(): void
    {
        // Arrange
        $captchaService = $this->createMock(CaptchaService::class);
        $captchaService->expects(static::never())->method('reset');
        $captchaService->method('generate')->willReturn(self::CAPTCHA_IMAGE);

        $form = $this->factory([SecurityMeasure::ImageCaptcha], captchaService: $captchaService)->create(HumanCheckType::class, null, [
            'context' => self::CONTEXT,
        ]);

        // Act
        $form->createView();
        $form->createView();

        // Assert
        static::assertSame(self::CAPTCHA_IMAGE, $form->createView()->vars['captchaImage']);
    }

    public function testTheViewCarriesNoCaptchaImageWhenTheMeasureIsOff(): void
    {
        // Act
        $view = $this->humanCheck([SecurityMeasure::Honeypot])->createView();

        // Assert
        static::assertNull($view->vars['captchaImage']);
        static::assertFalse($view->vars['powEnabled']);
    }

    public function testAnUntouchedFormPassesEveryEnabledMeasure(): void
    {
        // Arrange
        $form = $this->humanCheck([SecurityMeasure::Honeypot, SecurityMeasure::SubmitTiming], captchaValid: true);
        $stamp = $this->signer->issue(self::CONTEXT, 18);
        $this->clock->modify('+5 seconds');

        // Act
        $form->submit([HumanCheckType::HONEYPOT_FIELD => '', 'stamp' => $stamp]);

        // Assert
        static::assertSame([], $this->blocks);
        static::assertSame([SecurityMeasure::Honeypot, SecurityMeasure::SubmitTiming], $this->passes);
        static::assertCount(0, $form->getErrors(true));
    }

    public function testAFilledHoneypotIsBlockedAndLogged(): void
    {
        // Arrange
        $form = $this->humanCheck([SecurityMeasure::Honeypot]);

        // Act
        $form->submit([HumanCheckType::HONEYPOT_FIELD => 'https://spam.example']);

        // Assert
        static::assertSame([[SecurityMeasure::Honeypot, 'filled']], $this->blocks);
        static::assertCount(1, $form->getErrors(true));
    }

    public function testASubmissionFasterThanTwoSecondsIsBlocked(): void
    {
        // Arrange
        $form = $this->humanCheck([SecurityMeasure::SubmitTiming]);
        $stamp = $this->signer->issue(self::CONTEXT, 18);
        $this->clock->modify('+300 milliseconds');

        // Act
        $form->submit(['stamp' => $stamp]);

        // Assert
        static::assertSame([[SecurityMeasure::SubmitTiming, 'too_fast']], $this->blocks);
    }

    public function testAMissingStampIsBlocked(): void
    {
        // Arrange
        $form = $this->humanCheck([SecurityMeasure::SubmitTiming]);

        // Act
        $form->submit(['stamp' => '']);

        // Assert
        static::assertSame([[SecurityMeasure::SubmitTiming, 'missing_stamp']], $this->blocks);
    }

    public function testAReplayedStampIsBlocked(): void
    {
        // Arrange
        $stamp = $this->signer->issue(self::CONTEXT, 18);
        $this->clock->modify('+5 seconds');
        $this->humanCheck([SecurityMeasure::SubmitTiming])->submit(['stamp' => $stamp]);
        $this->blocks = [];

        // Act
        $this->humanCheck([SecurityMeasure::SubmitTiming])->submit(['stamp' => $stamp]);

        // Assert
        static::assertSame([[SecurityMeasure::SubmitTiming, 'nonce_reused']], $this->blocks);
    }

    public function testAWrongCaptchaCodeAttachesItsErrorToTheCaptchaField(): void
    {
        // Arrange
        $form = $this->humanCheck([SecurityMeasure::ImageCaptcha], captchaValid: false);

        // Act
        $form->submit(['captcha' => 'zzzz']);

        // Assert
        static::assertSame([[SecurityMeasure::ImageCaptcha, 'wrong_code']], $this->blocks);
        static::assertCount(1, $form->get('captcha')->getErrors());
        static::assertCount(0, $form->getErrors());
    }

    public function testProofOfWorkRejectsAMissingProofAndAcceptsAValidOne(): void
    {
        // Arrange
        $difficulty = 16;
        $missing = $this->humanCheck([SecurityMeasure::ProofOfWork], difficulty: $difficulty);
        $stamp = $this->signer->issue(self::CONTEXT, $difficulty);
        $nonce = (string) $this->signer->verify($stamp, self::CONTEXT)['nonce'];
        $proof = $this->solve($nonce, $difficulty);

        // Act
        $missing->submit(['stamp' => $this->signer->issue(self::CONTEXT, $difficulty), 'proof' => '']);
        $blocksAfterMissing = $this->blocks;

        $this->blocks = [];
        $valid = $this->humanCheck([SecurityMeasure::ProofOfWork], difficulty: $difficulty);
        $valid->submit(['stamp' => $stamp, 'proof' => $proof]);

        // Assert
        static::assertSame([[SecurityMeasure::ProofOfWork, 'missing_proof']], $blocksAfterMissing);
        static::assertSame([], $this->blocks);
        static::assertSame([SecurityMeasure::ProofOfWork], $this->passes);
    }

    public function testAStampClaimingZeroDifficultyStillHasToDoTheConfiguredWork(): void
    {
        // Arrange
        $form = $this->humanCheck([SecurityMeasure::ProofOfWork], difficulty: 16);
        $forged = $this->signer->issue(self::CONTEXT, 0);

        // Act
        $form->submit(['stamp' => $forged, 'proof' => 'anything']);

        // Assert
        static::assertSame([[SecurityMeasure::ProofOfWork, 'invalid_proof']], $this->blocks);
    }

    public function testOneGenericErrorIsShownEvenWhenTwoMeasuresBlock(): void
    {
        // Arrange
        $form = $this->humanCheck([SecurityMeasure::Honeypot, SecurityMeasure::SubmitTiming]);

        // Act
        $form->submit([HumanCheckType::HONEYPOT_FIELD => 'spam', 'stamp' => '']);

        // Assert
        static::assertCount(2, $this->blocks);
        static::assertCount(1, $form->getErrors());
    }

    public function testABlockedSubmissionRaisesOneEventWithItsDistinctReasons(): void
    {
        // Arrange
        $form = $this->humanCheck([SecurityMeasure::Honeypot, SecurityMeasure::SubmitTiming, SecurityMeasure::ProofOfWork]);

        // Act
        $form->submit([HumanCheckType::HONEYPOT_FIELD => 'spam', 'stamp' => '', 'proof' => '']);

        // Assert
        static::assertCount(1, $this->events);
        static::assertSame(SecurityEventType::FormMeasure, $this->events[0][0]);
        static::assertSame(['context' => self::CONTEXT, 'reasons' => ['filled', 'missing_stamp']], $this->events[0][1]);
    }

    #[DataProvider('crossSiteHeaderProvider')]
    public function testACrossSiteSubmissionIsLoggedButRaisesNoEvent(string $header, string $value): void
    {
        // Arrange
        $this->request->headers->set($header, $value);
        $form = $this->humanCheck([SecurityMeasure::Honeypot]);

        // Act
        $form->submit([HumanCheckType::HONEYPOT_FIELD => 'spam']);

        // Assert
        static::assertSame([[SecurityMeasure::Honeypot, 'filled']], $this->blocks);
        static::assertSame([], $this->events);
    }

    public static function crossSiteHeaderProvider(): Generator
    {
        yield 'foreign origin' => ['Origin', 'https://evil.example'];
        yield 'opaque origin' => ['Origin', 'null'];
        yield 'fetch metadata cross-site' => ['Sec-Fetch-Site', 'cross-site'];
        yield 'fetch metadata same-site' => ['Sec-Fetch-Site', 'same-site'];
    }

    #[DataProvider('ownSiteHeaderProvider')]
    public function testAnOwnSiteOrHeaderlessSubmissionIsScored(string $header, string $value): void
    {
        // Arrange
        if ($header !== '') {
            $this->request->headers->set($header, $value);
        }
        $form = $this->humanCheck([SecurityMeasure::Honeypot]);

        // Act
        $form->submit([HumanCheckType::HONEYPOT_FIELD => 'spam']);

        // Assert
        static::assertCount(1, $this->events);
    }

    public static function ownSiteHeaderProvider(): Generator
    {
        yield 'no browser headers, a script' => ['', ''];
        yield 'own origin' => ['Origin', 'http://localhost'];
        yield 'fetch metadata same-origin' => ['Sec-Fetch-Site', 'same-origin'];
    }

    public function testAPassingSubmissionRaisesNoEvent(): void
    {
        // Arrange
        $form = $this->humanCheck([SecurityMeasure::Honeypot]);

        // Act
        $form->submit([HumanCheckType::HONEYPOT_FIELD => '']);

        // Assert
        static::assertSame([], $this->events);
    }

    public function testAnExpiredStampIsReportedAsExpiredNotInvalid(): void
    {
        // Arrange
        $form = $this->humanCheck([SecurityMeasure::SubmitTiming]);
        $stamp = $this->signer->issue(self::CONTEXT, 18);
        $this->clock->modify('+3 hours');

        // Act
        $form->submit(['stamp' => $stamp]);

        // Assert
        static::assertSame([[SecurityMeasure::SubmitTiming, 'expired_stamp']], $this->blocks);
    }

    /**
     * @param list<SecurityMeasure> $enabled
     */
    private function humanCheck(array $enabled, bool $captchaValid = true, int $difficulty = 18): FormInterface
    {
        return $this->factory($enabled, $captchaValid, $difficulty)->create(HumanCheckType::class, null, ['context' => self::CONTEXT]);
    }

    /**
     * @param list<SecurityMeasure> $enabled
     */
    private function factory(array $enabled = [], bool $captchaValid = true, int $difficulty = 18, ?CaptchaService $captchaService = null): FormFactoryInterface
    {
        $measureSettings = $this->createStub(MeasureSettings::class);
        $measureSettings->method('isEnabled')->willReturnCallback(static fn(SecurityMeasure $measure): bool => in_array($measure, $enabled, true));
        $measureSettings->method('proofOfWorkDifficulty')->willReturn($difficulty);

        if ($captchaService === null) {
            $stub = $this->createStub(CaptchaService::class);
            $stub->method('isValid')->willReturn($captchaValid ? null : 'security.captcha_wrong');
            $stub->method('generate')->willReturn(self::CAPTCHA_IMAGE);
            $captchaService = $stub;
        }

        $measureLogger = $this->createStub(MeasureLogger::class);
        $measureLogger
            ->method('recordPass')
            ->willReturnCallback(function (SecurityMeasure $measure): void {
                $this->passes[] = $measure;
            });
        $measureLogger
            ->method('recordBlock')
            ->willReturnCallback(function (SecurityMeasure $measure, ?string $context, $request, ?array $detail): void {
                $this->blocks[] = [$measure, (string) ($detail['reason'] ?? '')];
            });

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $requestStack = new RequestStack();
        $requestStack->push($this->request);

        $securityService = $this->createStub(SecurityService::class);
        $securityService
            ->method('event')
            ->willReturnCallback(function (SecurityEventType $type, Request $request, array $context): void {
                $this->events[] = [$type, $context];
            });

        $type = new HumanCheckType($measureSettings, $captchaService, $this->signer, $measureLogger, $requestStack, $translator, $securityService);

        return Forms::createFormFactoryBuilder()->addExtension(new ValidatorExtension(Validation::createValidator()))->addType($type)->getFormFactory();
    }

    private function solve(string $nonce, int $difficulty): string
    {
        for ($counter = 0; $counter < 5000000; $counter++) {
            if ($this->signer->isProofValid($nonce, (string) $counter, $difficulty)) {
                return (string) $counter;
            }
        }

        static::fail('no proof found for difficulty ' . $difficulty);
    }
}
