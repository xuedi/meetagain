<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Types;

use App\Emails\Types\VerificationRequestEmail;
use App\Entity\User;
use App\Service\Config\ConfigService;
use App\Service\Http\RequestHostResolver;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\MailerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Tests\Unit\Emails\SampleFactoryTrait;

class VerificationRequestEmailTest extends TestCase
{
    use SampleFactoryTrait;

    public function testTheComposedMessageLeavesHostAndUrlToTheQueue(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@platform.example.com'));

        $host = $this->createStub(RequestHostResolver::class);
        $host->method('getSchemeAndHost')->willReturn('https://dragondescendants.example.com');
        $host->method('getHost')->willReturn('dragondescendants.example.com');

        $user = $this->createStub(User::class);
        $user->method('getEmail')->willReturn('user@example.com');
        $user->method('getLocale')->willReturn('en');
        $user->method('getRegcode')->willReturn('TOKEN123');
        $user->method('getName')->willReturn('Alice');

        $email = new VerificationRequestEmail(
            $this->createStub(BlocklistInterface::class),
            $this->mockSampleFactory(),
            $this->createStub(MailerInterface::class),
            $config,
            $host,
        );

        // Act
        $messages = $email->compose(['user' => $user]);

        // Assert
        static::assertCount(1, $messages);
        $context = $messages[0]->getContext();
        static::assertArrayNotHasKey('host', $context);
        static::assertArrayNotHasKey('url', $context);
    }
}
