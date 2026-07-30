<?php declare(strict_types=1);

namespace Tests\Unit\Service\Email;

use App\Emails\TemplateDefinition;
use App\Emails\TemplateProviderInterface;
use App\ExtendedFilesystem;
use App\Repository\EmailTemplateRepository;
use App\Service\Email\EmailTemplateService;
use PHPUnit\Framework\TestCase;

final class TemplateProviderChainTest extends TestCase
{
    private const string PROJECT_DIR = '/project';

    public function testAProvidedTemplateJoinsTheShippedDefaults(): void
    {
        // Arrange
        $service = $this->makeService([$this->makeProvider('plugin_receipt', 'Your receipt')]);

        // Act
        $templates = $service->getDefaultTemplates('en');

        // Assert
        $this->assertArrayHasKey('welcome', $templates);
        $this->assertArrayHasKey('plugin_receipt', $templates);
        $this->assertSame('Your receipt', $templates['plugin_receipt']['subject']);
        $this->assertSame(['amount'], $templates['plugin_receipt']['variables']);
    }

    public function testEachProviderIsAskedForTheLanguageBeingSeeded(): void
    {
        // Arrange
        $provider = $this->createMock(TemplateProviderInterface::class);
        $provider->expects($this->once())->method('getDefinitions')->with('de')->willReturn([]);

        // Act
        $this->makeService([$provider])->getDefaultTemplates('de');

        // Assert
        $this->assertTrue(true);
    }

    public function testAProviderMayReplaceAShippedTemplate(): void
    {
        // Arrange
        $service = $this->makeService([$this->makeProvider('welcome', 'A warmer welcome')]);

        // Act
        $templates = $service->getDefaultTemplates('en');

        // Assert
        $this->assertSame('A warmer welcome', $templates['welcome']['subject']);
    }

    /** @param list<TemplateProviderInterface> $providers */
    private function makeService(array $providers): EmailTemplateService
    {
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn('<p>shipped body</p>');

        return new EmailTemplateService(
            $this->createStub(EmailTemplateRepository::class),
            $fs,
            self::PROJECT_DIR,
            $providers,
        );
    }

    private function makeProvider(string $identifier, string $subject): TemplateProviderInterface
    {
        $provider = $this->createStub(TemplateProviderInterface::class);
        $provider->method('getDefinitions')->willReturn([
            new TemplateDefinition($identifier, $subject, '<p>provided body</p>', ['amount']),
        ]);

        return $provider;
    }
}
