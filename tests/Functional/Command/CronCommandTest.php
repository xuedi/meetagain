<?php declare(strict_types=1);

namespace Tests\Functional\Command;

use App\Entity\EmailQueue;
use App\Enum\EmailQueueStatus;
use App\Service\Email\EmailService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CronCommandTest extends KernelTestCase
{
    public function testEmailServiceCronTaskSendsQueuedEmails(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Arrange: queue a pending email
        $email = new EmailQueue();
        $email->setSender('sender@example.com');
        $email->setRecipient('recipient@example.com');
        $email->setSubject('Test Cron');
        $email->setLang('en');
        $email->setCreatedAt(new DateTimeImmutable());
        $email->setRenderedBody('Test Body');
        $email->setStatus(EmailQueueStatus::Pending);
        $em->persist($email);
        $em->flush();

        $emailId = $email->getId();
        static::assertNotNull($emailId);

        // Act: drive only the EmailService cron task (not the full app:cron pipeline)
        $emailService = $container->get(EmailService::class);
        $sentCount = $emailService->sendQueue();
        static::assertSame('1', $sentCount);

        // Assert
        $em->clear();
        $updatedEmail = $em->getRepository(EmailQueue::class)->find($emailId);
        static::assertSame(EmailQueueStatus::Sent, $updatedEmail->getStatus(), 'Email status should be sent');
        static::assertNotNull($updatedEmail->getProviderDispatchedAt(), 'Email should have a dispatched date');
    }
}
