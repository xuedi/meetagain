<?php declare(strict_types=1);

namespace Tests\Functional\Command;

use App\Entity\EmailQueue;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CronCommandTest extends KernelTestCase
{
    public function testCronCommandSendsQueuedEmails(): void
    {
        $kernel = self::bootKernel();
        $em = $kernel->getContainer()->get('doctrine')->getManager();

        // 1. Prepare: Add an unsent email to the queue
        $email = new EmailQueue();
        $email->setSender('sender@example.com');
        $email->setRecipient('recipient@example.com');
        $email->setSubject('Test Cron');
        $email->setLang('en');
        $email->setCreatedAt(new DateTimeImmutable());
        $email->setRenderedBody('Test Body');
        $email->setSendAt(null);

        $em->persist($email);
        $em->flush();

        $emailId = $email->getId();
        $this->assertNotNull($emailId);

        // 2. Act: Run the app:cron command
        $application = new Application($kernel);
        $command = $application->find('app:cron');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Send out queued emails ... OK', $commandTester->getDisplay());

        // 3. Assert: Check if email is marked as sent
        $em->clear();
        $updatedEmail = $em->getRepository(EmailQueue::class)->find($emailId);
        $this->assertNotNull($updatedEmail->getSendAt(), 'Email should have a send date');
    }
}
