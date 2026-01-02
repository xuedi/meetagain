<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\EmailTemplate;
use App\Entity\EmailTemplateTranslation;
use App\Enum\EmailType;
use App\Repository\EmailTemplateRepository;
use App\Service\EmailTemplateService;
use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

class EmailTemplateServiceTest extends TestCase
{
    private Stub&EmailTemplateRepository $repoStub;
    private EmailTemplateService $subject;

    protected function setUp(): void
    {
        $this->repoStub = $this->createStub(EmailTemplateRepository::class);
        $projectDir = dirname(__DIR__, 3);
        $this->subject = new EmailTemplateService(repo: $this->repoStub, projectDir: $projectDir);
    }

    public function testGetTemplateReturnsTemplateWhenFound(): void
    {
        // Arrange
        $template = $this->makeTemplate('welcome', 'Welcome!', '<h1>Hello</h1>');
        $this->repoStub->method('findByIdentifier')->willReturn($template);

        // Act
        $result = $this->subject->getTemplate(EmailType::Welcome);

        // Assert
        $this->assertSame($template, $result);
    }

    public function testGetTemplateReturnsNullWhenNotFound(): void
    {
        // Arrange
        $this->repoStub->method('findByIdentifier')->willReturn(null);

        // Act
        $result = $this->subject->getTemplate(EmailType::VerificationRequest);

        // Assert
        $this->assertNull($result);
    }

    public function testGetTemplateContentReturnsContentForRequestedLanguage(): void
    {
        // Arrange
        $template = $this->makeTemplate('welcome', 'Welcome!', '<h1>Hello</h1>');
        $this->repoStub->method('findByIdentifier')->willReturn($template);

        // Act
        $result = $this->subject->getTemplateContent(EmailType::Welcome, 'en');

        // Assert
        $this->assertSame('Welcome!', $result['subject']);
        $this->assertSame('<h1>Hello</h1>', $result['body']);
    }

    public function testGetTemplateContentFallsBackToEnglish(): void
    {
        // Arrange
        $template = $this->makeTemplate('welcome', 'Welcome!', '<h1>Hello</h1>');
        $this->repoStub->method('findByIdentifier')->willReturn($template);

        // Act - request German, but only English exists
        $result = $this->subject->getTemplateContent(EmailType::Welcome, 'de');

        // Assert - should fall back to English
        $this->assertSame('Welcome!', $result['subject']);
        $this->assertSame('<h1>Hello</h1>', $result['body']);
    }

    public function testGetTemplateContentThrowsWhenTemplateNotFound(): void
    {
        // Arrange
        $this->repoStub->method('findByIdentifier')->willReturn(null);

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Email template "welcome" not found');

        // Act
        $this->subject->getTemplateContent(EmailType::Welcome, 'en');
    }

    public function testGetTemplateContentThrowsWhenNoTranslationFound(): void
    {
        // Arrange - template without any translations
        $template = new EmailTemplate();
        $template->setIdentifier('welcome');
        $template->setAvailableVariables(['username']);
        $template->setUpdatedAt(new DateTimeImmutable());
        $this->repoStub->method('findByIdentifier')->willReturn($template);

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No translation found for email template');

        // Act
        $this->subject->getTemplateContent(EmailType::Welcome, 'en');
    }

    #[DataProvider('renderContentProvider')]
    public function testRenderContentReplacesPlaceholders(string $content, array $context, string $expected): void
    {
        // Act
        $result = $this->subject->renderContent($content, $context);

        // Assert
        $this->assertSame($expected, $result);
    }

    public static function renderContentProvider(): Generator
    {
        yield 'single placeholder' => [
            'Hello {{username}}!',
            ['username' => 'Alice'],
            'Hello Alice!',
        ];
        yield 'multiple placeholders' => [
            '{{username}} reset token: {{token}}',
            ['username' => 'Bob', 'token' => 'abc123'],
            'Bob reset token: abc123',
        ];
        yield 'placeholder used twice' => [
            '{{name}} and {{name}} again',
            ['name' => 'Test'],
            'Test and Test again',
        ];
        yield 'no placeholders' => [
            'Plain text content',
            ['unused' => 'value'],
            'Plain text content',
        ];
        yield 'missing placeholder in context' => [
            'Hello {{username}} with {{missing}}',
            ['username' => 'Alice'],
            'Hello Alice with {{missing}}',
        ];
        yield 'empty context' => [
            'Hello {{world}}',
            [],
            'Hello {{world}}',
        ];
        yield 'integer value in context' => [
            'Event ID: {{eventId}}',
            ['eventId' => 42],
            'Event ID: 42',
        ];
    }

    public function testRenderContentIgnoresNonScalarValues(): void
    {
        // Arrange
        $content = 'Object: {{obj}}, Array: {{arr}}';
        $context = [
            'obj' => new stdClass(),
            'arr' => ['a', 'b'],
        ];

        // Act
        $result = $this->subject->renderContent($content, $context);

        // Assert: non-scalar values should not be replaced
        $this->assertSame('Object: {{obj}}, Array: {{arr}}', $result);
    }

    public function testGetDefaultTemplatesReturnsAllTemplates(): void
    {
        // Act
        $templates = $this->subject->getDefaultTemplates();

        // Assert
        $this->assertCount(7, $templates);
        $this->assertArrayHasKey('verification_request', $templates);
        $this->assertArrayHasKey('welcome', $templates);
        $this->assertArrayHasKey('password_reset_request', $templates);
        $this->assertArrayHasKey('notification_message', $templates);
        $this->assertArrayHasKey('notification_rsvp_aggregated', $templates);
        $this->assertArrayHasKey('notification_event_canceled', $templates);
        $this->assertArrayHasKey('announcement', $templates);
    }

    public function testGetDefaultTemplatesContainsRequiredKeys(): void
    {
        // Act
        $templates = $this->subject->getDefaultTemplates();

        // Assert
        foreach ($templates as $identifier => $template) {
            $this->assertArrayHasKey('subject', $template, "Template '$identifier' missing 'subject'");
            $this->assertArrayHasKey('body', $template, "Template '$identifier' missing 'body'");
            $this->assertArrayHasKey('variables', $template, "Template '$identifier' missing 'variables'");
            $this->assertIsArray($template['variables'], "Template '$identifier' variables should be array");
        }
    }

    private function makeTemplate(string $identifier, string $subject, string $body): EmailTemplate
    {
        $template = new EmailTemplate();
        $template->setIdentifier($identifier);
        $template->setAvailableVariables(['username', 'host']);
        $template->setUpdatedAt(new DateTimeImmutable());

        // Create English translation
        $translation = new EmailTemplateTranslation();
        $translation->setEmailTemplate($template);
        $translation->setLanguage('en');
        $translation->setSubject($subject);
        $translation->setBody($body);
        $translation->setUpdatedAt(new DateTimeImmutable());
        $template->addTranslation($translation);

        return $template;
    }
}
