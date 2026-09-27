<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Types;

use App\Emails\Types\SupportEmailVerifyEmail;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Http\RequestHostResolver;
use DateTimeImmutable;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\MailerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Tests\Unit\Emails\SampleFactoryTrait;

class SupportEmailVerifyEmailTest extends TestCase
{
    use SampleFactoryTrait;

    private const string REQUESTER_NAME = 'Mallory Attacker';
    private const string REQUESTER_MESSAGE = 'Click here to claim your prize.';

    public function testContextCarriesNoRequesterSuppliedField(): void
    {
        // Arrange
        $emailType = $this->createEmailType();

        // Act
        $messages = $emailType->compose([
            'email' => 'victim@example.com',
            'token' => str_repeat('a', 64),
            'expiresAt' => new DateTimeImmutable('2026-01-02 12:00:00'),
            'lang' => 'en',
            'name' => self::REQUESTER_NAME,
            'message' => self::REQUESTER_MESSAGE,
        ]);

        // Assert
        static::assertCount(1, $messages);
        $context = $messages[0]->getContext();
        static::assertSame(['lang', 'token', 'expiresAt'], array_keys($context));
        static::assertStringNotContainsString(self::REQUESTER_NAME, implode("\n", $context));
        static::assertStringNotContainsString(self::REQUESTER_MESSAGE, implode("\n", $context));
    }

    public function testIdentifier(): void
    {
        // Arrange
        $emailType = $this->createEmailType();

        // Act & Assert
        static::assertSame(EmailType::SupportEmailVerify->value, $emailType->getIdentifier());
    }

    public function testMockDataCarriesNoRequesterSuppliedField(): void
    {
        // Arrange
        $emailType = $this->createEmailType();

        // Act
        $mock = $emailType->getDisplayMockData('en');

        // Assert
        static::assertSame(['host', 'url', 'lang', 'token', 'expiresAt'], array_keys($mock['context']));
    }

    private function createEmailType(): SupportEmailVerifyEmail
    {
        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@platform.example.com'));

        $host = $this->createStub(RequestHostResolver::class);
        $host->method('getSchemeAndHost')->willReturn('https://platform.example.com');
        $host->method('getHost')->willReturn('platform.example.com');

        return new SupportEmailVerifyEmail(
            $this->createStub(BlocklistInterface::class),
            $this->mockSampleFactory(),
            $this->createStub(MailerInterface::class),
            $config,
            $host,
        );
    }
}
