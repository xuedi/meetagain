<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Types;

use App\Emails\EmailQueueInterface;
use App\Emails\Types\SupportNotificationEmail;
use App\Entity\SupportRequest;
use App\Entity\User;
use App\Enum\ContactType;
use App\Enum\EmailType;
use App\Repository\UserRepository;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

class SupportNotificationEmailTest extends TestCase
{
    public function testSendEnqueuesOneEmailPerAdmin(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@platform.example.com'));

        $admin1 = $this->createStub(User::class);
        $admin1->method('getEmail')->willReturn('admin1@example.com');

        $admin2 = $this->createStub(User::class);
        $admin2->method('getEmail')->willReturn('admin2@example.com');

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findAdminUsers')->willReturn([$admin1, $admin2]);

        $enqueuedEmails = [];
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue
            ->expects($this->exactly(2))
            ->method('enqueue')
            ->with(
                $this->anything(),
                $this->callback(static function (TemplatedEmail $email) use (&$enqueuedEmails): bool {
                    $enqueuedEmails[] = $email;
                    return true;
                }),
                EmailType::SupportNotification,
                $this->anything(),
            );

        $request = $this->makeRequest();

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('General inquiry');

        $emailType = new SupportNotificationEmail(
            $this->createStub(BlocklistCheckerInterface::class),
            $queue,
            $config,
            $userRepo,
            $this->createStub(LoggerInterface::class),
            $translator,
        );

        // Act
        $emailType->send(['request' => $request]);

        // Assert
        static::assertCount(2, $enqueuedEmails);
        static::assertSame('admin1@example.com', $enqueuedEmails[0]->getTo()[0]->getAddress());
        static::assertSame('admin2@example.com', $enqueuedEmails[1]->getTo()[0]->getAddress());
        static::assertSame('General inquiry', $enqueuedEmails[0]->getContext()['contactType']);
    }

    public function testSendLogsWarningAndEnqueuesNothingWhenNoAdmins(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@platform.example.com'));

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findAdminUsers')->willReturn([]);

        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->never())->method('enqueue');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with('Support ticket received but no active admin recipients found', $this->anything());

        $request = $this->makeRequest();

        $emailType = new SupportNotificationEmail($this->createStub(BlocklistCheckerInterface::class), $queue, $config, $userRepo, $logger, $this->createStub(TranslatorInterface::class));

        // Act
        $emailType->send(['request' => $request]);
    }

    private function makeRequest(): SupportRequest
    {
        $request = $this->createStub(SupportRequest::class);
        $request->method('getId')->willReturn(42);
        $request->method('getContactType')->willReturn(ContactType::General);
        $request->method('getName')->willReturn('John');
        $request->method('getEmail')->willReturn('john@example.com');
        $request->method('getMessage')->willReturn('Help!');
        $request->method('getCreatedAt')->willReturn(new DateTimeImmutable('2026-01-01'));

        return $request;
    }
}
