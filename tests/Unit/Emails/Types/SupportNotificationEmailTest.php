<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Types;

use App\Emails\Types\SupportNotificationEmail;
use App\Entity\SupportRequest;
use App\Entity\User;
use App\Enum\SupportAudience;
use App\Service\Config\ConfigService;
use App\Service\Support\RecipientResolver;
use DateTimeImmutable;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\MailerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Unit\Emails\SampleFactoryTrait;

class SupportNotificationEmailTest extends TestCase
{
    use SampleFactoryTrait;

    public function testOneMessageIsComposedPerResolvedRecipient(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@platform.example.com'));

        $admin1 = $this->createStub(User::class);
        $admin1->method('getEmail')->willReturn('admin1@example.com');

        $admin2 = $this->createStub(User::class);
        $admin2->method('getEmail')->willReturn('admin2@example.com');

        $resolver = $this->createStub(RecipientResolver::class);
        $resolver->method('resolve')->willReturn([$admin1, $admin2]);

        $request = $this->makeRequest();

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('The organizers');

        $emailType = new SupportNotificationEmail(
            $this->createStub(BlocklistInterface::class),
            $this->mockSampleFactory(),
            $this->createStub(MailerInterface::class),
            $config,
            $resolver,
            $this->createStub(LoggerInterface::class),
            $translator,
        );

        // Act
        $messages = $emailType->compose(['request' => $request]);

        // Assert
        static::assertCount(2, $messages);
        static::assertSame('admin1@example.com', $messages[0]->getTo()[0]->getAddress());
        static::assertSame('admin2@example.com', $messages[1]->getTo()[0]->getAddress());
        static::assertSame('The organizers', $messages[0]->getContext()['audience']);
    }

    public function testNobodyResolvedLogsAWarningAndComposesNothing(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@platform.example.com'));

        $resolver = $this->createStub(RecipientResolver::class);
        $resolver->method('resolve')->willReturn([]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with('Support ticket received but no recipients could be resolved', $this->anything());

        $request = $this->makeRequest();

        $emailType = new SupportNotificationEmail(
            $this->createStub(BlocklistInterface::class),
            $this->mockSampleFactory(),
            $this->createStub(MailerInterface::class),
            $config,
            $resolver,
            $logger,
            $this->createStub(TranslatorInterface::class),
        );

        // Act
        $messages = $emailType->compose(['request' => $request]);

        // Assert
        static::assertSame([], $messages);
    }

    public function testAGuestRequesterIsLabelledWithTheTranslatedGuestName(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@platform.example.com'));

        $admin = $this->createStub(User::class);
        $admin->method('getEmail')->willReturn('admin@example.com');
        $admin->method('getLocale')->willReturn('de');

        $resolver = $this->createStub(RecipientResolver::class);
        $resolver->method('resolve')->willReturn([$admin]);

        $request = $this->createStub(SupportRequest::class);
        $request->method('getAudience')->willReturn(SupportAudience::Organizer);
        $request->method('getRequesterLabel')->willReturn(null);
        $request->method('getEmail')->willReturn(null);
        $request->method('getMessage')->willReturn('Hallo');
        $request->method('getCreatedAt')->willReturn(new DateTimeImmutable('2026-01-01'));

        $translator = $this->createStub(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnMap([
                [SupportAudience::Organizer->label(), [], null, 'de', 'Die Organisatoren'],
                ['admin_support.requester_guest', [], null, 'de', 'Gast'],
            ]);

        $emailType = new SupportNotificationEmail(
            $this->createStub(BlocklistInterface::class),
            $this->mockSampleFactory(),
            $this->createStub(MailerInterface::class),
            $config,
            $resolver,
            $this->createStub(LoggerInterface::class),
            $translator,
        );

        // Act
        $messages = $emailType->compose(['request' => $request]);

        // Assert
        static::assertCount(1, $messages);
        static::assertSame('Gast', $messages[0]->getContext()['name']);
        static::assertNull($messages[0]->getContext()['email']);
    }

    private function makeRequest(): SupportRequest
    {
        $request = $this->createStub(SupportRequest::class);
        $request->method('getId')->willReturn(42);
        $request->method('getAudience')->willReturn(SupportAudience::Organizer);
        $request->method('getRequesterLabel')->willReturn('John');
        $request->method('getEmail')->willReturn('john@example.com');
        $request->method('getMessage')->willReturn('Help!');
        $request->method('getCreatedAt')->willReturn(new DateTimeImmutable('2026-01-01'));

        return $request;
    }
}
