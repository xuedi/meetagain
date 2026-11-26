<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Types;

use App\Emails\Types\SupportResponseEmail;
use App\Entity\SupportRequest;
use App\Enum\EmailType;
use App\Enum\SupportAudience;
use App\Service\Config\ConfigService;
use DateTimeImmutable;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\MailerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Tests\Unit\Emails\SampleFactoryTrait;

class SupportResponseEmailTest extends TestCase
{
    use SampleFactoryTrait;

    public function testTheMessageGoesToTheRequesterWithTheirQuestionAndTheAnswer(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@platform.example.com'));

        $blocklist = $this->createStub(BlocklistInterface::class);
        $blocklist->method('isBlocked')->willReturn(false);

        $emailType = new SupportResponseEmail(
            $blocklist,
            $this->mockSampleFactory(),
            $this->createStub(MailerInterface::class),
            $config,
            'en',
            $this->contentSanitizer(),
        );

        // Act
        $messages = $emailType->compose(['request' => $this->makeRequest(), 'response' => 'Here is your answer.']);

        // Assert
        static::assertCount(1, $messages);
        static::assertSame('john@example.com', $messages[0]->getTo()[0]->getAddress());
        $context = $messages[0]->getContext();
        static::assertSame('John', $context['name']);
        static::assertSame('Help!', $context['originalMessage']);
        static::assertSame('Here is your answer.', $context['response']);
        static::assertArrayHasKey('createdAt', $context);
    }

    public function testStoredTextReachesTheMailAsPlainTextSoTheEngineEscapesItOnce(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@platform.example.com'));
        $sanitizer = $this->contentSanitizer();
        $request = $this->makeRequest(message: $sanitizer->escape("It's <b> & co\nline two"));
        $emailType = new SupportResponseEmail(
            $this->createStub(BlocklistInterface::class),
            $this->mockSampleFactory(),
            $this->createStub(MailerInterface::class),
            $config,
            'en',
            $sanitizer,
        );

        // Act
        $messages = $emailType->compose(['request' => $request, 'response' => $sanitizer->basic("See you tomorrow's <b>evening</b> & bring tea\nBye")]);

        // Assert
        $context = $messages[0]->getContext();
        static::assertSame("It's <b> & co\nline two", $context['originalMessage']);
        static::assertSame("See you tomorrow's evening & bring tea\nBye", $context['response']);
    }

    public function testARequestWithNoLanguageAndNoRequesterFallsBackToTheInstallationDefault(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@platform.example.com'));
        $blocklist = $this->createStub(BlocklistInterface::class);
        $blocklist->method('isBlocked')->willReturn(false);

        $emailType = new SupportResponseEmail(
            $blocklist,
            $this->mockSampleFactory(),
            $this->createStub(MailerInterface::class),
            $config,
            'fr',
            $this->contentSanitizer(),
        );

        // Act
        $messages = $emailType->compose(['request' => $this->makeRequest(), 'response' => 'Here is your answer.']);

        // Assert
        static::assertSame('fr', $messages[0]->getLocale());
    }

    public function testIdentifier(): void
    {
        // Arrange
        $emailType = new SupportResponseEmail(
            $this->createStub(BlocklistInterface::class),
            $this->mockSampleFactory(),
            $this->createStub(MailerInterface::class),
            $this->createStub(ConfigService::class),
            'en',
            $this->contentSanitizer(),
        );

        // Act & Assert
        static::assertSame(EmailType::SupportResponse->value, $emailType->getIdentifier());
    }

    public function testGuardSkipsWhenTheAddressWasNeverConfirmed(): void
    {
        // Arrange
        $emailType = new SupportResponseEmail(
            $this->createStub(BlocklistInterface::class),
            $this->mockSampleFactory(),
            $this->createStub(MailerInterface::class),
            $this->createStub(ConfigService::class),
            'en',
            $this->contentSanitizer(),
        );

        // Act & Assert
        static::assertFalse($emailType->guardCheck(['request' => $this->makeRequest(verified: false)]));
    }

    private function makeRequest(bool $verified = true, string $message = 'Help!'): SupportRequest
    {
        $request = $this->createStub(SupportRequest::class);
        $request->method('getAudience')->willReturn(SupportAudience::Organizer);
        $request->method('getRequesterLabel')->willReturn('John');
        $request->method('getEmail')->willReturn('john@example.com');
        $request->method('getMessage')->willReturn($message);
        $request->method('getCreatedAt')->willReturn(new DateTimeImmutable('2026-01-01'));
        $request->method('isEmailVerified')->willReturn($verified);

        return $request;
    }
}
