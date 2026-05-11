<?php declare(strict_types=1);

namespace Tests\Unit\Service\Email;

use App\Entity\EmailTemplate;
use App\Entity\EmailTemplateTranslation;
use App\Enum\EmailType;
use App\ExtendedFilesystem;
use App\Repository\EmailTemplateRepository;
use App\Service\Email\EmailTemplateService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class EmailTemplateServiceTest extends TestCase
{
    private const string PROJECT_DIR = '/app';

    public function testGetDefaultTemplatesUsesLanguageSpecificTemplateWhenItExists(): void
    {
        // Arrange - language-prefixed paths exist; default paths also exist as fallback
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturnCallback(
            static fn (string $p): string => str_contains($p, '/de/') ? '<de>body:' . basename($p) : '<en>body:' . basename($p),
        );
        $service = new EmailTemplateService(
            $this->createStub(EmailTemplateRepository::class),
            $fs,
            self::PROJECT_DIR,
        );

        // Act
        $templates = $service->getDefaultTemplates('de');

        // Assert
        static::assertSame('<de>body:' . EmailType::Welcome->value . '.html', $templates[EmailType::Welcome->value]['body']);
    }

    public function testGetDefaultTemplatesFallsBackToDefaultTemplateWhenLanguageFileMissing(): void
    {
        // Arrange - only default (non-language) paths exist
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturnCallback(static fn (string $p): bool => !str_contains($p, '/de/'));
        $fs->method('getFileContents')->willReturnCallback(
            static fn (string $p): string => '<en>body:' . basename($p),
        );
        $service = new EmailTemplateService(
            $this->createStub(EmailTemplateRepository::class),
            $fs,
            self::PROJECT_DIR,
        );

        // Act
        $templates = $service->getDefaultTemplates('de');

        // Assert
        static::assertSame('<en>body:' . EmailType::Welcome->value . '.html', $templates[EmailType::Welcome->value]['body']);
    }

    public function testGetDefaultTemplatesThrowsWhenNeitherFileExists(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(false);
        $service = new EmailTemplateService(
            $this->createStub(EmailTemplateRepository::class),
            $fs,
            self::PROJECT_DIR,
        );

        // Assert
        $this->expectException(RuntimeException::class);

        // Act
        $service->getDefaultTemplates('de');
    }

    public function testGetTemplateContentReturnsRequestedLanguage(): void
    {
        // Arrange
        $template = $this->buildTemplate('de', 'Betreff', '<de>body');
        $repo = $this->createStub(EmailTemplateRepository::class);
        $repo->method('findByIdentifier')->willReturn($template);
        $service = new EmailTemplateService(
            $repo,
            $this->createStub(ExtendedFilesystem::class),
            self::PROJECT_DIR,
        );

        // Act
        $content = $service->getTemplateContent(EmailType::Welcome, 'de');

        // Assert
        static::assertSame(['subject' => 'Betreff', 'body' => '<de>body'], $content);
    }

    public function testGetTemplateContentFallsBackToEnglishWhenLanguageMissing(): void
    {
        // Arrange - only English translation exists
        $template = $this->buildTemplate('en', 'Welcome', '<en>body');
        $repo = $this->createStub(EmailTemplateRepository::class);
        $repo->method('findByIdentifier')->willReturn($template);
        $service = new EmailTemplateService(
            $repo,
            $this->createStub(ExtendedFilesystem::class),
            self::PROJECT_DIR,
        );

        // Act
        $content = $service->getTemplateContent(EmailType::Welcome, 'de');

        // Assert
        static::assertSame(['subject' => 'Welcome', 'body' => '<en>body'], $content);
    }

    public function testGetTemplateContentThrowsWhenTemplateMissing(): void
    {
        // Arrange
        $repo = $this->createStub(EmailTemplateRepository::class);
        $repo->method('findByIdentifier')->willReturn(null);
        $service = new EmailTemplateService(
            $repo,
            $this->createStub(ExtendedFilesystem::class),
            self::PROJECT_DIR,
        );

        // Assert
        $this->expectException(RuntimeException::class);

        // Act
        $service->getTemplateContent(EmailType::Welcome, 'en');
    }

    public function testGetTemplateContentThrowsWhenNoTranslationsExist(): void
    {
        // Arrange - template entity with zero translations
        $template = new EmailTemplate();
        $template->setIdentifier(EmailType::Welcome->value);
        $repo = $this->createStub(EmailTemplateRepository::class);
        $repo->method('findByIdentifier')->willReturn($template);
        $service = new EmailTemplateService(
            $repo,
            $this->createStub(ExtendedFilesystem::class),
            self::PROJECT_DIR,
        );

        // Assert
        $this->expectException(RuntimeException::class);

        // Act
        $service->getTemplateContent(EmailType::Welcome, 'en');
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('provideRenderCases')]
    public function testRenderContentSubstitutesScalars(string $template, array $context, string $expected): void
    {
        // Arrange
        $service = new EmailTemplateService(
            $this->createStub(EmailTemplateRepository::class),
            $this->createStub(ExtendedFilesystem::class),
            self::PROJECT_DIR,
        );

        // Act
        $rendered = $service->renderContent($template, $context);

        // Assert
        static::assertSame($expected, $rendered);
    }

    public static function provideRenderCases(): iterable
    {
        yield 'string substitution' => ['Hi {{name}}!', ['name' => 'Alice'], 'Hi Alice!'];
        yield 'int substitution' => ['Count: {{n}}', ['n' => 42], 'Count: 42'];
        yield 'bool substitution' => ['Flag: {{flag}}', ['flag' => true], 'Flag: 1'];
        yield 'float substitution' => ['Pi: {{pi}}', ['pi' => 3.14], 'Pi: 3.14'];
        yield 'non-scalar is skipped' => ['List: {{xs}}', ['xs' => [1, 2, 3]], 'List: {{xs}}'];
        yield 'object is skipped' => ['Obj: {{o}}', ['o' => new \stdClass()], 'Obj: {{o}}'];
        yield 'unknown placeholder left as-is' => ['Hi {{x}}', [], 'Hi {{x}}'];
        yield 'multiple substitutions' => ['{{a}} and {{b}}', ['a' => 'foo', 'b' => 'bar'], 'foo and bar'];
    }

    private function buildTemplate(string $language, string $subject, string $body): EmailTemplate
    {
        $translation = new EmailTemplateTranslation();
        $translation->setLanguage($language);
        $translation->setSubject($subject);
        $translation->setBody($body);

        $template = new EmailTemplate();
        $template->setIdentifier(EmailType::Welcome->value);
        $template->addTranslation($translation);

        return $template;
    }
}
