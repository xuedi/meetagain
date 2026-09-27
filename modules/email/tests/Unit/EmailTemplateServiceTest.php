<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Module\Email\Contract\TemplateDefinition;
use Module\Email\Contract\TemplateProviderInterface;
use Module\Email\Internal\EmailTemplateService;
use Module\Email\Internal\Entity\EmailTemplate;
use Module\Email\Internal\Entity\EmailTemplateTranslation;
use Module\Email\Internal\Repository\EmailTemplateRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

class EmailTemplateServiceTest extends TestCase
{
    private const string IDENTIFIER = 'digest';

    public function testGetTemplateContentReturnsRequestedLanguage(): void
    {
        // Arrange
        $service = $this->service($this->template('de', 'Betreff', '<de>body'));

        // Act
        $content = $service->getTemplateContent(self::IDENTIFIER, 'de');

        // Assert
        static::assertSame(['subject' => 'Betreff', 'body' => '<de>body'], $content);
    }

    public function testGetTemplateContentFallsBackToEnglishWhenLanguageMissing(): void
    {
        // Arrange
        $service = $this->service($this->template('en', 'Welcome', '<en>body'));

        // Act
        $content = $service->getTemplateContent(self::IDENTIFIER, 'de');

        // Assert
        static::assertSame(['subject' => 'Welcome', 'body' => '<en>body'], $content);
    }

    public function testGetTemplateContentThrowsWhenTemplateMissing(): void
    {
        // Arrange
        $service = $this->service(null);

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Email template "digest" not found');

        // Act
        $service->getTemplateContent(self::IDENTIFIER, 'en');
    }

    public function testGetTemplateContentThrowsWhenNoTranslationsExist(): void
    {
        // Arrange
        $template = new EmailTemplate();
        $template->setIdentifier(self::IDENTIFIER);
        $service = $this->service($template);

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No translation found for email template');

        // Act
        $service->getTemplateContent(self::IDENTIFIER, 'en');
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('provideRenderCases')]
    public function testRenderContentSubstitutesScalars(string $template, array $context, string $expected): void
    {
        // Arrange
        $service = $this->service(null);

        // Act
        $rendered = $service->renderContent(self::IDENTIFIER, $template, $context);

        // Assert
        static::assertSame($expected, $rendered);
    }

    public static function provideRenderCases(): iterable
    {
        yield 'string substitution' => ['Hi {{name}}!', ['name' => 'Alice'], 'Hi Alice!'];
        yield 'int substitution' => ['Count: {{n}}', ['n' => 42], 'Count: 42'];
        yield 'bool substitution' => ['Flag: {{flag}}', ['flag' => true], 'Flag: 1'];
        yield 'float substitution' => ['Pi: {{pi}}', ['pi' => 3.14], 'Pi: 3.14'];
        yield 'null becomes empty' => ['Mail: {{email}}', ['email' => null], 'Mail: '];
        yield 'array is skipped' => ['List: {{xs}}', ['xs' => [1, 2, 3]], 'List: {{xs}}'];
        yield 'object is skipped' => ['Obj: {{o}}', ['o' => new stdClass()], 'Obj: {{o}}'];
        yield 'unknown placeholder left as-is' => ['Hi {{x}}', [], 'Hi {{x}}'];
        yield 'placeholder used twice' => ['{{a}} and {{a}}', ['a' => 'foo'], 'foo and foo'];
    }

    public function testRenderContentEscapesMarkupInASubstitutedValue(): void
    {
        // Arrange
        $service = $this->service(null);

        // Act
        $result = $service->renderContent(self::IDENTIFIER, '<p>Hello {{username}}</p>', ['username' => '<a href="https://evil.example">Alice</a>']);

        // Assert
        static::assertSame('<p>Hello &lt;a href=&quot;https://evil.example&quot;&gt;Alice&lt;/a&gt;</p>', $result);
    }

    public function testRenderContentLetsADeclaredHtmlVariableThroughAsMarkup(): void
    {
        // Arrange
        $service = $this->service(null);

        // Act
        $result = $service->renderContent(self::IDENTIFIER, '<div>{{eventsHtml}}</div>', ['eventsHtml' => '<ul><li>A real fragment</li></ul>']);

        // Assert
        static::assertSame('<div><ul><li>A real fragment</li></ul></div>', $result);
    }

    public function testAnHtmlVariableOfOneTemplateIsEscapedInAnother(): void
    {
        // Arrange
        $service = $this->service(null);

        // Act
        $result = $service->renderContent('other', '<div>{{eventsHtml}}</div>', ['eventsHtml' => '<b>x</b>']);

        // Assert
        static::assertSame('<div>&lt;b&gt;x&lt;/b&gt;</div>', $result);
    }

    public function testRenderSubjectLeavesTheValueUnescaped(): void
    {
        // Arrange
        $service = $this->service(null);

        // Act
        $result = $service->renderSubject('Message from {{sender}}', ['sender' => 'Tom & Jerry']);

        // Assert
        static::assertSame('Message from Tom & Jerry', $result);
    }

    public function testGetDefaultTemplatesCarriesEachDefinitionsFields(): void
    {
        // Arrange
        $service = $this->service(null);

        // Act
        $templates = $service->getDefaultTemplates('en');

        // Assert
        static::assertSame(
            [
                self::IDENTIFIER => [
                    'subject' => 'Digest',
                    'body' => '<p>{{eventsHtml}}</p>',
                    'variables' => ['eventsHtml'],
                    'htmlVariables' => ['eventsHtml'],
                ],
                'other' => ['subject' => 'Other', 'body' => '<p>{{eventsHtml}}</p>', 'variables' => ['eventsHtml'], 'htmlVariables' => []],
            ],
            $templates,
        );
    }

    public function testRenderFillsSubjectAndBodyOfTheStoredTranslation(): void
    {
        // Arrange
        $service = $this->service($this->template('en', 'Hi {{name}}', '<p>{{name}}</p>{{eventsHtml}}'));

        // Act
        $rendered = $service->render(self::IDENTIFIER, 'de', ['name' => '<b>Ann</b>', 'eventsHtml' => '<ul></ul>']);

        // Assert
        static::assertSame(['subject' => 'Hi <b>Ann</b>', 'body' => '<p>&lt;b&gt;Ann&lt;/b&gt;</p><ul></ul>'], $rendered);
    }

    public function testSeedLanguageAddsTheDefaultOnlyWhereTheTranslationIsMissing(): void
    {
        // Arrange
        $stored = $this->template('en', 'Digest', '<p>stored</p>');
        $repo = $this->createStub(EmailTemplateRepository::class);
        $repo->method('findByIdentifier')->willReturnCallback(static fn(string $identifier): ?EmailTemplate => $identifier === self::IDENTIFIER
            ? $stored
            : null);
        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $service = $this->service(null, $repo, $em);

        // Act
        $service->seedLanguage('de');
        $service->seedLanguage('en');

        // Assert
        static::assertCount(1, $persisted);
        static::assertInstanceOf(EmailTemplateTranslation::class, $persisted[0]);
        static::assertSame('de', $persisted[0]->getLanguage());
        static::assertSame('Digest', $persisted[0]->getSubject());
        static::assertSame($stored, $persisted[0]->getEmailTemplate());
    }

    private function service(?EmailTemplate $stored, ?EmailTemplateRepository $repo = null, ?EntityManagerInterface $em = null): EmailTemplateService
    {
        if (!$repo instanceof EmailTemplateRepository) {
            $repo = $this->createStub(EmailTemplateRepository::class);
            $repo->method('findByIdentifier')->willReturn($stored);
        }

        $provider = $this->createStub(TemplateProviderInterface::class);
        $provider
            ->method('getDefinitions')
            ->willReturn([
                new TemplateDefinition(self::IDENTIFIER, 'Digest', '<p>{{eventsHtml}}</p>', ['eventsHtml'], ['eventsHtml']),
                new TemplateDefinition('other', 'Other', '<p>{{eventsHtml}}</p>', ['eventsHtml']),
            ]);

        return new EmailTemplateService($repo, $em ?? $this->createStub(EntityManagerInterface::class), [$provider]);
    }

    private function template(string $language, string $subject, string $body): EmailTemplate
    {
        $translation = new EmailTemplateTranslation();
        $translation->setLanguage($language);
        $translation->setSubject($subject);
        $translation->setBody($body);

        $template = new EmailTemplate();
        $template->setIdentifier(self::IDENTIFIER);
        $template->addTranslation($translation);

        return $template;
    }
}
