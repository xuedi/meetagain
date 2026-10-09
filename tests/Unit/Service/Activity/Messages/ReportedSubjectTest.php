<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Activity\MessageInterface;
use App\Activity\Messages\ReportedSubject;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

class ReportedSubjectTest extends TestCase
{
    #[DataProvider('provideRenderCases')]
    public function testRender(array $meta, string $expectedText, string $expectedHtml): void
    {
        // Arrange
        $subject = $this->makeMessage($meta);

        // Act
        $text = $subject->render();
        $html = $subject->render(true);

        // Assert
        static::assertSame(ReportedSubject::TYPE, $subject->getType());
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertSame($expectedText, $text);
        static::assertSame($expectedHtml, $html);
    }

    public static function provideRenderCases(): iterable
    {
        $base = ['subject_type' => 'user', 'subject_id' => 2, 'reason' => 'spam'];

        yield 'reason only' => [
            $base,
            'reported(report.subject_reason_spam)',
            'reported(<b>report.subject_reason_spam</b>)',
        ];
        yield 'reason and remarks' => [
            $base + ['remarks' => 'Keeps <i>messaging</i>'],
            'remarks(reported(report.subject_reason_spam), Keeps <i>messaging</i>)',
            'remarks(reported(<b>report.subject_reason_spam</b>), Keeps &lt;i&gt;messaging&lt;/i&gt;)',
        ];
        yield 'unknown reason falls back to its value' => [
            ['subject_type' => 'user', 'subject_id' => 2, 'reason' => 'retired'],
            'reported(retired)',
            'reported(<b>retired</b>)',
        ];
    }

    #[DataProvider('provideInvalidMeta')]
    public function testValidateRejects(array $meta, string $expectedMessage): void
    {
        // Arrange
        $subject = $this->makeMessage($meta);

        // Assert
        $this->expectExceptionObject(new InvalidArgumentException($expectedMessage));

        // Act
        $subject->validate();
    }

    public static function provideInvalidMeta(): iterable
    {
        yield 'missing subject type' => [['subject_id' => 2, 'reason' => 'spam'], "Missing 'subject_type' in meta in core.reported_subject"];
        yield 'missing subject id' => [['subject_type' => 'user', 'reason' => 'spam'], "Missing 'subject_id' in meta in core.reported_subject"];
        yield 'non-numeric subject id' => [
            ['subject_type' => 'user', 'subject_id' => 'x', 'reason' => 'spam'],
            "Value 'subject_id' has to be numeric in 'core.reported_subject'",
        ];
        yield 'missing reason' => [['subject_type' => 'user', 'subject_id' => 2], "Missing 'reason' in meta in core.reported_subject"];
        yield 'non-string remarks' => [
            ['subject_type' => 'user', 'subject_id' => 2, 'reason' => 'spam', 'remarks' => 5],
            "Value 'remarks' must be a string in 'core.reported_subject'",
        ];
    }

    private function makeMessage(array $meta): ReportedSubject
    {
        $translator = new class implements TranslatorInterface {
            use TranslatorTrait;

            public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return match ($id) {
                    'profile_social.activity_reported_subject' => 'reported(' . $parameters['%reason%'] . ')',
                    'profile_social.activity_reported_subject_remarks' => 'remarks(' . $parameters['%message%'] . ', ' . $parameters['%remarks%'] . ')',
                    default => (string) $id,
                };
            }
        };

        $subject = new ReportedSubject();
        $subject->injectServices($this->createStub(RouterInterface::class), $this->createStub(ImageHtmlRenderer::class), $translator, $meta);

        return $subject;
    }
}
