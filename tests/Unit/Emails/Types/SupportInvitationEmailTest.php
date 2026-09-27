<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Types;

use App\Emails\Types\SupportInvitationEmail;
use App\Entity\SupportRequest;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Support\RecipientResolver;
use DateTimeImmutable;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\MailerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Tests\Unit\Emails\SampleFactoryTrait;

class SupportInvitationEmailTest extends TestCase
{
    use SampleFactoryTrait;

    public function testEveryAdminIsInvitedRegardlessOfWhoOwnsTheRequest(): void
    {
        // Arrange
        $emailType = $this->createEmailType(admins: [
            $this->user('one@example.com', 'Admin One'),
            $this->user('two@example.com', 'Admin Two'),
        ]);

        // Act
        $messages = $emailType->compose(['request' => $this->request()]);

        // Assert
        static::assertSame(
            ['one@example.com', 'two@example.com'],
            array_map(static fn(TemplatedEmail $email): string => $email->getTo()[0]->getAddress(), $messages),
        );
    }

    public function testContextNamesTheStewardWhoInvited(): void
    {
        // Arrange
        $emailType = $this->createEmailType(admins: [$this->user('admin@example.com', 'Admin')]);

        // Act
        $messages = $emailType->compose(['request' => $this->request()]);

        // Assert
        static::assertCount(1, $messages);
        static::assertSame(['invitedBy', 'name', 'message', 'createdAt', 'requestId'], array_keys($messages[0]->getContext()));
        static::assertSame('Sam Steward', $messages[0]->getContext()['invitedBy']);
    }

    public function testNothingIsComposedWhenThereAreNoAdmins(): void
    {
        // Arrange
        $emailType = $this->createEmailType(admins: []);

        // Act
        $messages = $emailType->compose(['request' => $this->request()]);

        // Assert
        static::assertSame([], $messages);
    }

    public function testIdentifier(): void
    {
        // Arrange
        $emailType = $this->createEmailType(admins: []);

        // Act & Assert
        static::assertSame(EmailType::SupportInvitation->value, $emailType->getIdentifier());
    }

    /** @param User[] $admins */
    private function createEmailType(array $admins): SupportInvitationEmail
    {
        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@platform.example.com'));

        $resolver = $this->createStub(RecipientResolver::class);
        $resolver->method('resolveAdmins')->willReturn($admins);

        return new SupportInvitationEmail(
            $this->createStub(BlocklistInterface::class),
            $this->mockSampleFactory(),
            $this->createStub(MailerInterface::class),
            $config,
            $resolver,
            $this->createStub(LoggerInterface::class),
        );
    }

    private function request(): SupportRequest
    {
        $request = $this->createStub(SupportRequest::class);
        $request->method('getInvitedAdminsBy')->willReturn($this->user('steward@example.com', 'Sam Steward'));
        $request->method('getRequesterLabel')->willReturn('John Doe');
        $request->method('getMessage')->willReturn('Help!');
        $request->method('getCreatedAt')->willReturn(new DateTimeImmutable('2026-01-01 12:00:00'));
        $request->method('getId')->willReturn(42);

        return $request;
    }

    private function user(string $email, string $name): User
    {
        $user = $this->createStub(User::class);
        $user->method('getEmail')->willReturn($email);
        $user->method('getName')->willReturn($name);

        return $user;
    }
}
