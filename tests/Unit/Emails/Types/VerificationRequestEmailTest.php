<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Types;

use App\Emails\EmailQueueInterface;
use App\Emails\Types\VerificationRequestEmail;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use App\Service\Http\RequestHostResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

class VerificationRequestEmailTest extends TestCase
{
    public function testSendUsesRequestHostNotConfigHost(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@platform.example.com'));

        $host = $this->createStub(RequestHostResolver::class);
        $host->method('getSchemeAndHost')->willReturn('https://dragondescendants.example.com');
        $host->method('getHost')->willReturn('dragondescendants.example.com');

        $capturedEmail = null;
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->once())->method('enqueue')
            ->with(
                $this->anything(),
                $this->callback(static function (TemplatedEmail $email) use (&$capturedEmail): bool {
                    $capturedEmail = $email;
                    return true;
                }),
                EmailType::VerificationRequest,
                $this->anything(),
            );

        $user = $this->createStub(User::class);
        $user->method('getEmail')->willReturn('user@example.com');
        $user->method('getLocale')->willReturn('en');
        $user->method('getRegcode')->willReturn('TOKEN123');
        $user->method('getName')->willReturn('Alice');

        $email = new VerificationRequestEmail(
            $this->createStub(BlocklistCheckerInterface::class),
            $queue,
            $config,
            $host,
        );

        // Act
        $email->send(['user' => $user]);

        // Assert
        static::assertNotNull($capturedEmail);
        $context = $capturedEmail->getContext();
        static::assertSame('https://dragondescendants.example.com', $context['host']);
        static::assertSame('dragondescendants.example.com', $context['url']);
    }
}
