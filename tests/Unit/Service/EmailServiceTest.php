<?php
declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\EmailQueue;
use App\Entity\User;
use App\Repository\EmailQueueRepository;
use App\Service\ConfigService;
use App\Service\EmailService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

#[AllowMockObjectsWithoutExpectations]
final class EmailServiceTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private ConfigService&MockObject $config;
    private EmailQueueRepository&MockObject $mailRepo;
    private EntityManagerInterface&MockObject $em;

    private EmailService $service;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->config = $this->createMock(ConfigService::class);
        $this->mailRepo = $this->createMock(EmailQueueRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->service = new EmailService(
            $this->mailer,
            $this->config,
            $this->mailRepo,
            $this->em,
        );

        $this->config->method('getMailerAddress')->willReturn(new Address('sender@email.com', 'email sender'));
        $this->config->method('getHost')->willReturn('https://example.com');
        $this->config->method('getUrl')->willReturn('example.com');
    }

    public function testPrepareVerificationRequestEnqueuesEmailWithExpectedData(): void
    {
        $user = $this->makeUser('user@example.com', 'Alice', 'en', 'abc123');

        $this->em->expects($this->once())
            ->method('persist')
            ->with(
                $this->callback(function ($entity) {
                    $this->assertInstanceOf(EmailQueue::class, $entity);
                    /** @var EmailQueue $entity */
                    $this->assertSame('"email sender" <sender@email.com>', $entity->getSender());
                    $this->assertSame('user@example.com', $entity->getRecipient());
                    $this->assertSame('Please Confirm your Email', $entity->getSubject());
                    $this->assertSame('_emails/verification_request.html.twig', $entity->getTemplate());
                    $this->assertSame('en', $entity->getLang());

                    $ctx = $entity->getContext();
                    $this->assertSame('https://example.com', $ctx['host']);
                    $this->assertSame('abc123', $ctx['token']);
                    $this->assertSame('example.com', $ctx['url']);
                    $this->assertSame('Alice', $ctx['username']);
                    $this->assertSame('en', $ctx['lang']);

                    $this->assertNotNull($entity->getCreatedAt());
                    $this->assertNull($entity->getSendAt());
                    return true;
                })
            );
        $this->em->expects($this->once())->method('flush');

        $ok = $this->service->prepareVerificationRequest($user);
        $this->assertTrue($ok);
    }

    public function testPrepareWelcomeAndResetPasswordAlsoEnqueue(): void
    {
        $user = $this->makeUser('bob@example.com', 'Bob', 'de', 'reg-999');

        // persist called twice (welcome + reset)
        $this->em->expects($this->exactly(2))->method('persist');
        $this->em->expects($this->exactly(2))->method('flush');

        $this->assertTrue($this->service->prepareWelcome($user));
        $this->assertTrue($this->service->prepareResetPassword($user));
    }

    public function testSendQueueSendsPendingEmailsAndMarksAsSent(): void
    {
        $queued = (new EmailQueue())
            ->setSender('"email sender" <sender@email.com>')
            ->setRecipient('user@example.com')
            ->setSubject('Subject')
            ->setTemplate('_emails/verification_request.html.twig')
            ->setLang('en')
            ->setContext(['k' => 'v']);

        $this->mailRepo
            ->expects($this->once())
            ->method('findBy')
            ->with(['sendAt' => null], ['id' => 'ASC'], 1000)
            ->willReturn([$queued]);

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(TemplatedEmail::class));

        $this->em->expects($this->once())->method('persist')
            ->with(
                $this->callback(function ($entity) use ($queued) {
                    $this->assertSame($queued, $entity);
                    $this->assertInstanceOf(DateTime::class, $queued->getSendAt());
                    return true;
                })
            );
        $this->em->expects($this->once())->method('flush');

        $this->service->sendQueue();
    }

    private function makeUser(
        string $email,
        string $name = 'Alice',
        string $locale = 'en',
        string $regcode = 'token-123'
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setLocale($locale);
        $user->setRegcode($regcode);
        return $user;
    }
}
