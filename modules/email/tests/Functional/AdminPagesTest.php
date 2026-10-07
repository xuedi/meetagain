<?php declare(strict_types=1);

namespace Module\Email\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\MailerInterface;
use Module\Email\Contract\QueueStatus;
use Module\Email\Contract\SendlogInterface;
use Module\Email\Contract\SentEmail;
use Module\Email\Contract\TemplatesInterface;
use Module\Email\Internal\EmailService;
use Module\Email\Internal\Entity\EmailQueue;
use Module\Email\Tests\Stub\PushDispatcher;
use Module\Email\Tests\Stub\TriggeredEmail;
use PHPUnit\Framework\Attributes\DataProvider;
use SensitiveParameter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Module\Members;

final class AdminPagesTest extends WebTestCase
{
    use SeedsTemplates;

    private const string BASE = '/en/admin/email';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        self::seedTemplates(self::$kernel);
    }

    #[DataProvider('providePages')]
    public function testAGuestIsSentAwayFromEveryPage(string $path): void
    {
        // Act
        $this->client->request('GET', self::BASE . $path);

        // Assert
        self::assertResponseRedirects();
    }

    #[DataProvider('providePages')]
    public function testAMemberWhoIsNoAdminIsRefusedEveryPage(string $path): void
    {
        // Arrange
        $this->client->loginUser(new Members(self::getContainer()->get(EntityManagerInterface::class))->member('Member'));

        // Act
        $this->client->request('GET', self::BASE . $path);

        // Assert
        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('providePages')]
    public function testEveryPageRendersForAnAdmin(string $path): void
    {
        // Arrange
        $this->loginAsAdmin();

        // Act
        $this->client->request('GET', self::BASE . $path);

        // Assert
        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providePages(): iterable
    {
        yield 'templates' => ['/templates'];
        yield 'planned' => ['/planned'];
        yield 'debugging' => ['/debugging'];
        yield 'sendlog' => ['/sendlog'];
        yield 'blocklist' => ['/blocklist'];
        yield 'blocklist add' => ['/blocklist/add'];
    }

    public function testTheTemplateListShowsTheProvidedTemplateAndItsEditFormSaves(): void
    {
        // Arrange
        $this->loginAsAdmin();
        $path = $this->templatePath();
        $crawler = $this->client->request('GET', $path . '/edit');

        // Act
        $this->client->submit($crawler
            ->filter('form[name="email_template"]')
            ->form([
                'email_template[subject-en]' => 'Updated subject',
                'email_template[body-en]' => '<h1>Updated body</h1>',
            ]));

        // Assert
        self::assertResponseRedirects($path . '/edit');
        self::assertSame('Updated subject', $this->storedSubject());
    }

    public function testResettingATemplateRestoresTheProvidedDefault(): void
    {
        // Arrange
        $this->loginAsAdmin();
        $path = $this->templatePath();
        $original = $this->storedSubject();
        $crawler = $this->client->request('GET', $path . '/edit');
        $this->client->submit($crawler->filter('form[name="email_template"]')->form(['email_template[subject-en]' => 'Changed']));
        $token = $this->client->followRedirect()->filter('a[href*="/reset"][data-post]')->attr('data-csrf-token');

        // Act
        $this->client->request('POST', $path . '/reset', ['_token' => $token]);

        // Assert
        self::assertResponseRedirects();
        self::assertNotSame('Changed', $original);
        self::assertSame($original, $this->storedSubject());
    }

    public function testThePreviewRendersTheMockDataThroughTheTemplate(): void
    {
        // Arrange
        $this->loginAsAdmin();

        // Act
        $crawler = $this->client->request('GET', $this->templatePath() . '/preview');

        // Assert
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('<li>mock</li>', (string) $crawler->html());
    }

    public function testADebuggingSendQueuesTheMessageForTheCronToDispatch(): void
    {
        // Arrange
        $this->loginAsAdmin();

        // Act
        $this->debuggingSend('debug@module-test.example');

        // Assert
        self::assertResponseRedirects();
        $row = $this->rowFor('debug@module-test.example');
        self::assertSame(QueueStatus::Pending, $row?->status);
        self::assertSame(TriggeredEmail::IDENTIFIER, $row?->template);
    }

    public function testADebuggingSendPingsNoPushDispatcher(): void
    {
        // Arrange
        $this->loginAsAdmin();

        // Act
        $this->debuggingSend('debug@module-test.example');

        // Assert
        self::assertNotNull($this->rowFor('debug@module-test.example'));
        self::assertSame([], self::getContainer()->get(PushDispatcher::class)->pings);
    }

    public function testADebuggingSendRefusesABlockedRecipient(): void
    {
        // Arrange
        $this->loginAsAdmin();
        self::getContainer()->get(BlocklistInterface::class)->add('blocked@module-test.example', 'bounced');

        // Act
        $this->debuggingSend('blocked@module-test.example');

        // Assert
        self::assertResponseRedirects();
        self::assertNull($this->rowFor('blocked@module-test.example'));
    }

    public function testADebuggingSendWithoutAValidTokenIsRejected(): void
    {
        // Arrange
        $this->loginAsAdmin();

        // Act
        $this->debuggingSend('forged@module-test.example', 'invalid-csrf-token');

        // Assert
        self::assertResponseStatusCodeSame(400);
        self::assertNull($this->rowFor('forged@module-test.example'));
    }

    public function testASendlogRowOpensWithItsDetails(): void
    {
        // Arrange
        $this->loginAsAdmin();
        self::getContainer()
            ->get(MailerInterface::class)
            ->send(self::getContainer()->get(TriggeredEmail::class), ['recipients' => ['shown@module-test.example']]);
        $row = $this->rowFor('shown@module-test.example');
        self::assertInstanceOf(SentEmail::class, $row);

        // Act
        $crawler = $this->client->request('GET', self::BASE . '/sendlog/' . $row->id);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('shown@module-test.example', $crawler->text());
    }

    public function testTheManualSyncTakesTheProviderStatus(): void
    {
        // Arrange
        $this->loginAsAdmin();
        self::getContainer()
            ->get(MailerInterface::class)
            ->send(self::getContainer()->get(TriggeredEmail::class), ['recipients' => ['synced@module-test.example']]);
        self::getContainer()->get(EmailService::class)->sendQueue();

        $sync = $this->client->request('GET', self::BASE . '/sendlog')->filter('a[href$="/sendlog/sync"][data-post]');

        // Act
        $this->client->request('POST', self::BASE . '/sendlog/sync', ['_token' => $sync->attr('data-csrf-token')]);

        // Assert
        self::assertResponseRedirects(self::BASE . '/sendlog');
        self::assertSame('delivered', $this->rowFor('synced@module-test.example')?->providerStatus);
    }

    public function testTheManualSyncWithoutAValidTokenIsRejected(): void
    {
        // Arrange
        $this->loginAsAdmin();

        // Act
        $this->client->request('POST', self::BASE . '/sendlog/sync', ['_token' => 'invalid-csrf-token']);

        // Assert
        self::assertResponseStatusCodeSame(400);
    }

    public function testAnAdminAddsAndRemovesABlocklistEntry(): void
    {
        // Arrange
        $this->loginAsAdmin();
        $blocklist = self::getContainer()->get(BlocklistInterface::class);
        $crawler = $this->client->request('GET', self::BASE . '/blocklist/add');
        $this->client->submit($crawler
            ->filter('form[name="email_blocklist"]')
            ->form([
                'email_blocklist[email]' => 'Added@Module-Test.Example',
                'email_blocklist[reason]' => 'asked to stop',
            ]));
        $added = $blocklist->reasonFor('added@module-test.example');
        $delete = $this->client->followRedirect()->filter('a[href$="/delete"][data-post]');

        // Act
        $this->client->request('POST', (string) $delete->attr('href'), ['_token' => $delete->attr('data-csrf-token')]);

        // Assert
        self::assertSame('asked to stop', $added);
        self::assertResponseRedirects(self::BASE . '/blocklist');
        self::assertNull($blocklist->reasonFor('added@module-test.example'));
    }

    public function testALateMailIsReleasedFromItsCapFromItsPage(): void
    {
        // Arrange
        $this->loginAsAdmin();
        self::getContainer()
            ->get(MailerInterface::class)
            ->send(self::getContainer()->get(TriggeredEmail::class), ['recipients' => ['late@module-test.example']]);
        $id = (int) $this->rowFor('late@module-test.example')?->id;
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->find(EmailQueue::class, $id)?->setStatus(QueueStatus::Late);
        $em->flush();
        $clear = $this->client->request('GET', self::BASE . '/sendlog/' . $id)->filter('a[href$="/clear-cap"][data-post]');

        // Act
        $this->client->request('POST', (string) $clear->attr('href'), ['_token' => $clear->attr('data-csrf-token')]);

        // Assert
        self::assertResponseRedirects(self::BASE . '/sendlog/' . $id);
        self::assertSame(
            QueueStatus::Pending->value,
            self::getContainer()->get(Connection::class)->fetchOne('SELECT status FROM mod_email_queue WHERE id = ?', [$id]),
        );
    }

    public function testABlocklistEntryIsNotRemovedWithAForgedToken(): void
    {
        // Arrange
        $this->loginAsAdmin();
        $blocklist = self::getContainer()->get(BlocklistInterface::class);
        $blocklist->add('kept@module-test.example', 'bounced');
        $delete = $this->client->request('GET', self::BASE . '/blocklist')->filter('a[href$="/delete"][data-post]');

        // Act
        $this->client->request('POST', (string) $delete->attr('href'), ['_token' => 'invalid-csrf-token']);

        // Assert
        self::assertResponseStatusCodeSame(400);
        self::assertSame('bounced', $blocklist->reasonFor('kept@module-test.example'));
    }

    public function testATemplateIsNotResetWithAForgedToken(): void
    {
        // Arrange
        $this->loginAsAdmin();
        $path = $this->templatePath();
        $crawler = $this->client->request('GET', $path . '/edit');
        $this->client->submit($crawler->filter('form[name="email_template"]')->form(['email_template[subject-en]' => 'Changed']));

        // Act
        $this->client->request('POST', $path . '/reset', ['_token' => 'invalid-csrf-token']);

        // Assert
        self::assertResponseStatusCodeSame(400);
        self::assertSame('Changed', $this->storedSubject());
    }

    private function loginAsAdmin(): void
    {
        $this->client->loginUser(new Members(self::getContainer()->get(EntityManagerInterface::class))->admin('Admin'));
    }

    private function templatePath(): string
    {
        $edit = $this->client
            ->request('GET', self::BASE . '/templates')
            ->filter('.admin-section-header[data-target="email-section-' . TriggeredEmail::IDENTIFIER . '"] a[href$="/edit"]');
        self::assertCount(1, $edit);

        return substr((string) $edit->attr('href'), 0, -strlen('/edit'));
    }

    private function storedSubject(): string
    {
        return self::getContainer()->get(TemplatesInterface::class)->render(TriggeredEmail::IDENTIFIER, 'en', [])['subject'];
    }

    private function debuggingSend(string $recipient, #[SensitiveParameter] ?string $token = null): void
    {
        $token ??= (string) $this->client
            ->request('GET', self::BASE . '/debugging')
            ->filter('form[action$="/debugging/send"] input[name="_token"]')
            ->attr('value');
        $this->client->request('POST', self::BASE . '/debugging/send', [
            '_token' => $token,
            'emailType' => TriggeredEmail::IDENTIFIER,
            'recipient' => $recipient,
            'language' => 'en',
            'context' => ['name' => 'Tester', 'itemsHtml' => ''],
        ]);
    }

    private function rowFor(string $recipient): ?SentEmail
    {
        return self::getContainer()->get(SendlogInterface::class)->list(recipient: $recipient)[0] ?? null;
    }
}
