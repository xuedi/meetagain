<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Entity\EmailBlocklistEntry;
use App\Entity\EmailQueue;
use App\Entity\EmailTemplate;
use App\Enum\EmailQueueStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminEmailTest extends WebTestCase
{
    private const ADMIN_EMAIL = 'Admin@example.org';
    private const ADMIN_PASSWORD = '1234';

    public function testEmailTemplateListRequiresAuthentication(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/admin/email/templates');

        // Assert
        $this->assertResponseRedirects();
    }

    public function testEmailTemplateListLoadsForAdmin(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/email/templates');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertGreaterThan(0, $crawler->filter('table')->count(), 'Templates table should exist');
    }

    public function testEmailTemplateEditPageLoads(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $template = $this->getFirstTemplate($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/email/templates/' . $template->getId() . '/edit');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertGreaterThan(0, $crawler->filter('form')->count(), 'Edit form should exist');
        $this->assertSelectorExists('input[name="email_template[subject-en]"]');
        $this->assertSelectorExists('textarea[name="email_template[body-en]"]');
    }

    public function testEmailTemplatePreviewPageLoads(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $template = $this->getFirstTemplate($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/email/templates/' . $template->getId() . '/preview');

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.box .level .level-left strong', $template->getIdentifier());
    }

    public function testEmailTemplateEditSubmitsSuccessfully(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $template = $this->getFirstTemplate($client);

        $crawler = $client->request('GET', '/en/admin/email/templates/' . $template->getId() . '/edit');

        // Act
        $form = $crawler
            ->filter('form[name="email_template"]')
            ->form([
                'email_template[subject-en]' => 'Updated Subject',
                'email_template[body-en]' => '<h1>Updated Body</h1>',
            ]);
        $client->submit($form);

        // Assert
        $this->assertResponseRedirects('/en/admin/email/templates/' . $template->getId() . '/edit');

        $client->followRedirect();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $updated = $em->getRepository(EmailTemplate::class)->find($template->getId());
        static::assertSame('Updated Subject', $updated->getSubject('en'));
    }

    public function testEmailTemplateResetToDefault(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $template = $this->getFirstTemplate($client);
        $originalSubject = $template->getSubject('en');

        $crawler = $client->request('GET', '/en/admin/email/templates/' . $template->getId() . '/edit');
        $form = $crawler
            ->filter('form[name="email_template"]')
            ->form([
                'email_template[subject-en]' => 'Modified Subject',
                'email_template[body-en]' => '<p>Modified</p>',
            ]);
        $client->submit($form);
        $client->followRedirect();

        // Act
        $editCrawler = $client->getCrawler();
        $token = $editCrawler->filter('a[href*="/reset"][data-post]')->attr('data-csrf-token');
        $client->request('POST', '/en/admin/email/templates/' . $template->getId() . '/reset', ['_token' => $token]);

        // Assert
        $this->assertResponseRedirects();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $reset = $em->getRepository(EmailTemplate::class)->find($template->getId());
        static::assertSame($originalSubject, $reset->getSubject('en'));
    }

    public function testPlannedEmailsPageLoadsForAdmin(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/email/planned');

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testDebuggingTestSendQueuesInsteadOfSending(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $recipient = 'debug-queued@example.test';

        // Act
        $client->request('POST', '/en/admin/email/debugging/send', [
            'emailType' => 'welcome',
            'recipient' => $recipient,
            'language' => 'en',
            'context' => ['name' => 'Test Person'],
        ]);

        // Assert
        $this->assertResponseRedirects();
        $row = $this->findQueueRow($client, $recipient);
        static::assertInstanceOf(EmailQueue::class, $row, 'The test send must leave a queue row for cron to dispatch');
        static::assertSame(EmailQueueStatus::Pending, $row->getStatus());
        static::assertSame('welcome', $row->getTemplate());
    }

    public function testDebuggingTestSendRefusesABlockedRecipient(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $recipient = 'debug-blocked@example.test';
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->persist(new EmailBlocklistEntry()
            ->setEmail($recipient)
            ->setReason('functional test')
            ->setAddedAt(new DateTimeImmutable()));
        $em->flush();

        // Act
        $client->request('POST', '/en/admin/email/debugging/send', [
            'emailType' => 'welcome',
            'recipient' => $recipient,
            'language' => 'en',
            'context' => [],
        ]);

        // Assert
        $this->assertResponseRedirects();
        static::assertNull($this->findQueueRow($client, $recipient), 'A blocked recipient must not be queued');
    }

    private function findQueueRow($client, string $recipient): ?EmailQueue
    {
        return $client
            ->getContainer()
            ->get(EntityManagerInterface::class)
            ->getRepository(EmailQueue::class)
            ->findOneBy(['recipient' => $recipient]);
    }

    private function loginAsAdmin($client): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler
            ->selectButton('Login')
            ->form([
                '_username' => self::ADMIN_EMAIL,
                '_password' => self::ADMIN_PASSWORD,
            ]);
        $client->submit($form);
        $client->followRedirect();
    }

    private function getFirstTemplate($client): EmailTemplate
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $templates = $em->getRepository(EmailTemplate::class)->findAll();
        $this->assertNotEmpty($templates, 'At least one email template should exist');

        return $templates[0];
    }
}
