<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\EmailTemplate;
use App\Enum\EmailType;
use App\Repository\EmailTemplateRepository;
use App\Service\EmailTemplateService;
use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use stdClass;

class EmailTemplateServiceTest extends TestCase
{
    private Stub&EmailTemplateRepository $repoStub;
    private EmailTemplateService $subject;

    protected function setUp(): void
    {
        $this->repoStub = $this->createStub(EmailTemplateRepository::class);
        $this->subject = new EmailTemplateService(repo: $this->repoStub);
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
        $this->assertCount(6, $templates);
        $this->assertArrayHasKey('verification_request', $templates);
        $this->assertArrayHasKey('welcome', $templates);
        $this->assertArrayHasKey('password_reset_request', $templates);
        $this->assertArrayHasKey('notification_message', $templates);
        $this->assertArrayHasKey('notification_rsvp_aggregated', $templates);
        $this->assertArrayHasKey('notification_event_canceled', $templates);
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
        $template->setSubject($subject);
        $template->setBody($body);
        $template->setAvailableVariables(['username', 'host']);
        $template->setUpdatedAt(new DateTimeImmutable());

        return $template;
    }
}
