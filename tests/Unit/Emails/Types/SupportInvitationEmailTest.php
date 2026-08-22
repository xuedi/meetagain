<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Types;

use App\Emails\EmailQueueInterface;
use App\Emails\Types\SupportInvitationEmail;
use App\Entity\SupportRequest;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use App\Service\Support\RecipientResolver;
use DateTimeImmutable;
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
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->exactly(2))->method('enqueue');

        $emailType = $this->createEmailType($queue, admins: [
            $this->user('one@example.com', 'Admin One'),
            $this->user('two@example.com', 'Admin Two'),
        ]);

        // Act
        $emailType->send(['request' => $this->request()]);
    }

    public function testContextNamesTheStewardWhoInvited(): void
    {
        // Arrange
        $enqueued = null;
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue
            ->expects($this->once())
            ->method('enqueue')
            ->with(
                $this->anything(),
                $this->callback(static function (TemplatedEmail $email) use (&$enqueued): bool {
                    $enqueued = $email;
                    return true;
                }),
                $this->anything(),
            );

        $emailType = $this->createEmailType($queue, admins: [$this->user('admin@example.com', 'Admin')]);

        // Act
        $emailType->send(['request' => $this->request()]);

        // Assert
        static::assertInstanceOf(TemplatedEmail::class, $enqueued);
        static::assertSame(['invitedBy', 'name', 'message', 'createdAt', 'requestId'], array_keys($enqueued->getContext()));
        static::assertSame('Sam Steward', $enqueued->getContext()['invitedBy']);
    }

    public function testNothingIsSentWhenThereAreNoAdmins(): void
    {
        // Arrange
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->never())->method('enqueue');

        $emailType = $this->createEmailType($queue, admins: []);

        // Act
        $emailType->send(['request' => $this->request()]);
    }

    public function testIdentifier(): void
    {
        // Arrange
        $emailType = $this->createEmailType($this->createStub(EmailQueueInterface::class), admins: []);

        // Act & Assert
        static::assertSame(EmailType::SupportInvitation->value, $emailType->getIdentifier());
    }

    /** @param User[] $admins */
    private function createEmailType(EmailQueueInterface $queue, array $admins): SupportInvitationEmail
    {
        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@platform.example.com'));

        $resolver = $this->createStub(RecipientResolver::class);
        $resolver->method('resolveAdmins')->willReturn($admins);

        return new SupportInvitationEmail(
            $this->createStub(BlocklistCheckerInterface::class),
            $this->mockSampleFactory(),
            $queue,
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
