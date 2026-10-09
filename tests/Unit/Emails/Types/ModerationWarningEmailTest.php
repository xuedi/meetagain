<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Types;

use App\Emails\Types\ModerationWarningEmail;
use App\Entity\ModerationReport;
use App\Enum\EmailType;
use App\Enum\ModerationReportReason;
use App\Service\Config\ConfigService;
use DateTimeImmutable;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\MailerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Unit\Emails\SampleFactoryTrait;
use Tests\Unit\Stubs\UserStub;

class ModerationWarningEmailTest extends TestCase
{
    use SampleFactoryTrait;

    private const string REPORTER_REMARKS = 'I am the reporter and this is private.';
    private const string EXCERPT = 'Text the reporter quoted.';

    public function testContextCarriesNeitherTheReporterNorTheExcerpt(): void
    {
        // Arrange
        $author = new UserStub()
            ->setId(2)
            ->setName('Orlando')
            ->setEmail('orlando@example.org')
            ->setLocale('de');
        $report = new ModerationReport('comment', 11, ModerationReportReason::Spam, new DateTimeImmutable('2026-10-01'))
            ->setAuthor($author)
            ->setReporter(
                new UserStub()
                    ->setId(1)
                    ->setName('Reporter Rita'),
            )
            ->setExcerpt(self::EXCERPT)
            ->setRemarks(self::REPORTER_REMARKS);
        $emailType = $this->createEmailType();

        // Act
        $messages = $emailType->compose(['report' => $report, 'note' => 'Please stop posting ads.']);

        // Assert
        static::assertCount(1, $messages);
        $context = $messages[0]->getContext();
        static::assertSame(['name', 'reason', 'note', 'lang'], array_keys($context));
        static::assertSame('de', $context['lang']);
        static::assertSame('Please stop posting ads.', $context['note']);
        $joined = implode("\n", $context);
        static::assertStringNotContainsString('Reporter Rita', $joined);
        static::assertStringNotContainsString(self::REPORTER_REMARKS, $joined);
        static::assertStringNotContainsString(self::EXCERPT, $joined);
        static::assertSame('orlando@example.org', $messages[0]->getTo()[0]->getAddress());
    }

    public function testADeletedAuthorGetsNoMail(): void
    {
        // Arrange
        $report = new ModerationReport('user', 2, ModerationReportReason::Spam, new DateTimeImmutable('2026-10-01'));
        $emailType = $this->createEmailType();

        // Act
        $messages = $emailType->compose(['report' => $report, 'note' => 'x']);

        // Assert
        static::assertSame([], $messages);
    }

    public function testIdentifier(): void
    {
        // Arrange
        $emailType = $this->createEmailType();

        // Act & Assert
        static::assertSame(EmailType::ModerationWarning->value, $emailType->getIdentifier());
    }

    private function createEmailType(): ModerationWarningEmail
    {
        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@platform.example.com'));
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new ModerationWarningEmail(
            $this->createStub(BlocklistInterface::class),
            $this->mockSampleFactory(),
            $this->createStub(MailerInterface::class),
            $config,
            $translator,
        );
    }
}
