<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Entity\EmailTemplate;
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
        // Form fields are now language-specific
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
        $this->assertSelectorTextContains('.title', 'Preview');
    }

    public function testEmailTemplateEditSubmitsSuccessfully(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $template = $this->getFirstTemplate($client);

        $crawler = $client->request('GET', '/en/admin/email/templates/' . $template->getId() . '/edit');

        // Act - select by form name to avoid the navbar logout button (also a submit button)
        $form = $crawler
            ->filter('form[name="email_template"]')
            ->form([
                'email_template[subject-en]' => 'Updated Subject',
                'email_template[body-en]' => '<h1>Updated Body</h1>',
            ]);
        $client->submit($form);

        // Assert
        $this->assertResponseRedirects('/en/admin/email/templates');

        // Verify changes persisted
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

        // First change the template — select by form name to avoid the navbar logout button
        $crawler = $client->request('GET', '/en/admin/email/templates/' . $template->getId() . '/edit');
        $form = $crawler
            ->filter('form[name="email_template"]')
            ->form([
                'email_template[subject-en]' => 'Modified Subject',
                'email_template[body-en]' => '<p>Modified</p>',
            ]);
        $client->submit($form);
        $client->followRedirect();

        // Act: reset to default
        $client->request('POST', '/en/admin/email/templates/' . $template->getId() . '/reset');

        // Assert
        $this->assertResponseRedirects();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $reset = $em->getRepository(EmailTemplate::class)->find($template->getId());
        static::assertSame($originalSubject, $reset->getSubject('en'));
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
