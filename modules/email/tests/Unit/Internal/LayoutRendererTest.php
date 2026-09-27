<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit\Internal;

use App\Service\Config\ConfigService;
use Module\Email\Contract\SendingIdentity;
use Module\Email\Internal\Entity\EmailQueue;
use Module\Email\Internal\LayoutRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;

final class LayoutRendererTest extends TestCase
{
    public function testWrapsTheStoredFragmentInAFullDocument(): void
    {
        // Arrange
        $mail = $this->queued('<p>Rendered email body</p>');

        // Act
        $html = $this->renderer()->wrap($mail);

        // Assert
        static::assertStringStartsWith('<!DOCTYPE html>', $html);
        static::assertStringContainsString('<p>Rendered email body</p>', $html);
        static::assertStringEndsWith('</html>', trim($html));
    }

    public function testHeaderCarriesTheLogoAndTheFooterTheSiteIdentity(): void
    {
        // Arrange
        $mail = $this->queued('<p>body</p>');

        // Act
        $html = $this->renderer()->wrap($mail);

        // Assert
        static::assertStringContainsString('https://example.org/logo.png', $html);
        static::assertStringContainsString('alt="Example Site"', $html);
        static::assertStringContainsString('https://example.org/en/imprint', $html);
    }

    public function testTheHeaderCarriesNoMimePartOnlyTheSitesOwnLogoEndpoint(): void
    {
        // Arrange
        $mail = $this->queued('<p>body</p>');

        // Act
        $html = $this->renderer()->wrap($mail);

        // Assert
        static::assertStringContainsString('src="https://example.org/logo.png"', $html);
        static::assertStringNotContainsString('cid:', $html);
    }

    public function testASnapshotWithoutALogoRendersNoImageAtAll(): void
    {
        // Arrange
        $mail = $this->queued('<p>body</p>', ['logoUrl' => null]);

        // Act
        $html = $this->renderer()->wrap($mail);

        // Assert
        static::assertStringNotContainsString('<img', $html);
        static::assertStringContainsString('<p>body</p>', $html);
    }

    /**
     * @return iterable<string, array{0: ?string}>
     */
    public static function unusableBodyProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'never rendered' => [null];
        yield 'unclosed tag' => ['<div><p>half a paragraph'];
        yield 'not html at all' => ['{{ this is not a template }}'];
    }

    #[DataProvider('unusableBodyProvider')]
    public function testAnEmptyOrMalformedBodyStillProducesADocument(?string $body): void
    {
        // Arrange
        $mail = $this->queued($body);

        // Act
        $html = $this->renderer()->wrap($mail);

        // Assert
        static::assertStringStartsWith('<!DOCTYPE html>', $html);
        static::assertStringContainsString('</body>', $html);
        static::assertStringEndsWith('</html>', trim($html));
    }

    public function testARowWithoutAStoredSnapshotSendsTheBareBodyAndSaysSoOutLoud(): void
    {
        // Arrange
        $mail = new EmailQueue()
            ->setSubject('Subject')
            ->setLang('en')
            ->setContext([])
            ->setRenderedBody('<p>legacy row</p>');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with(static::stringContains('no frozen layout'), static::anything());

        // Act
        $html = $this->renderer(logger: $logger)->wrap($mail);

        // Assert
        static::assertSame('<p>legacy row</p>', $html);
    }

    public function testAFrozenRowRendersWithEveryLiveResolverReplacedByOneThatThrows(): void
    {
        // Arrange
        $mail = $this->queued('<p>body</p>', [
            'siteName' => 'Second Site',
            'siteUrl' => 'https://second.example',
            'logoUrl' => 'https://second.example/logo.png',
            'links' => [['label' => 'Imprint', 'url' => 'https://second.example/en/imprint']],
            'attribution' => 'Sent by <a href="https://second.example">Second Site</a> - a group on the <a href="https://example.org">MeetAgain</a> platform',
        ]);

        // Act
        $html = $this->hostileRenderer()->wrap($mail);

        // Assert
        static::assertStringContainsString('Second Site', $html);
        static::assertStringContainsString('https://second.example', $html);
        static::assertStringContainsString('a group on the', $html);
        static::assertStringContainsString('Imprint', $html);
        static::assertStringNotContainsString('Example Site', $html);
    }

    public function testABrokenLayoutTemplateFallsBackToTheBareBody(): void
    {
        // Arrange
        $twig = new Environment(new ArrayLoader(['@Email/layout.html.twig' => "{{ include('does-not-exist.html.twig') }}"]));
        $twig->addExtension(new TranslationExtension(new Translator('en')));
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('error');
        $mail = $this->queued('<p>body</p>');

        // Act
        $html = $this->renderer(twig: $twig, logger: $loggerMock)->wrap($mail);

        // Assert
        static::assertSame('<p>body</p>', $html);
    }

    public function testTheSnapshotFreezesTheIdentityAndTheThemeAccent(): void
    {
        // Arrange
        $identity = new SendingIdentity(siteName: 'Example Site', siteUrl: 'https://example.org', logoUrl: 'https://example.org/logo.png', links: [[
            'label' => 'Imprint',
            'url' => 'https://example.org/en/imprint',
        ]]);

        // Act
        $snapshot = $this->renderer()->snapshot($identity);

        // Assert
        static::assertSame(
            [
                'siteName' => 'Example Site',
                'siteUrl' => 'https://example.org',
                'logoUrl' => 'https://example.org/logo.png',
                'accent' => '#123456',
                'links' => [['label' => 'Imprint', 'url' => 'https://example.org/en/imprint']],
            ],
            $snapshot,
        );
    }

    private function queued(?string $body, array $overrides = []): EmailQueue
    {
        return new EmailQueue()
            ->setSubject('Subject')
            ->setLang('en')
            ->setRenderedBody($body)
            ->setContext([
                LayoutRenderer::CONTEXT_KEY => [
                    ...[
                        'siteName' => 'Example Site',
                        'siteUrl' => 'https://example.org',
                        'logoUrl' => 'https://example.org/logo.png',
                        'accent' => '#123456',
                        'links' => [['label' => 'Imprint', 'url' => 'https://example.org/en/imprint']],
                    ],
                    ...$overrides,
                ],
            ]);
    }

    public function testAStoredAttributionReplacesTheDefaultSentByLine(): void
    {
        // Arrange
        $attribution = 'Sent by <a href="https://second.example">Second Site</a> - a group on the <a href="https://example.org">Example Site</a> platform';
        $mail = $this->queued('<p>body</p>', ['attribution' => $attribution]);

        // Act
        $html = $this->renderer()->wrap($mail);

        // Assert
        static::assertStringContainsString($attribution, $html);
        static::assertStringNotContainsString('Sent by <a href="https://example.org">Example Site</a></', $html);
    }

    public function testASiteNameCarryingMarkupIsEscapedInTheDefaultFooter(): void
    {
        // Arrange
        $mail = $this->queued('<p>body</p>', ['siteName' => '<script>alert(1)</script>']);

        // Act
        $html = $this->renderer()->wrap($mail);

        // Assert
        static::assertStringNotContainsString('<script>alert(1)</script>', $html);
        static::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    private function hostileRenderer(): LayoutRenderer
    {
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getThemeColors')->willThrowException(new RuntimeException('The send path resolved the sending identity live'));

        $twig = new Environment($this->moduleTemplates());
        $twig->addExtension(new TranslationExtension(new Translator('en')));

        return new LayoutRenderer(twig: $twig, configService: $configService, logger: $this->createStub(LoggerInterface::class));
    }

    private function renderer(?Environment $twig = null, ?LoggerInterface $logger = null): LayoutRenderer
    {
        if ($twig === null) {
            $twig = new Environment($this->moduleTemplates());
            $twig->addExtension(new TranslationExtension(new Translator('en')));
        }

        $configService = $this->createStub(ConfigService::class);
        $configService->method('getThemeColors')->willReturn(['color_link' => '#123456']);

        return new LayoutRenderer(twig: $twig, configService: $configService, logger: $logger ?? $this->createStub(LoggerInterface::class));
    }

    private function moduleTemplates(): FilesystemLoader
    {
        $loader = new FilesystemLoader();
        $loader->addPath(dirname(__DIR__, 3) . '/templates', 'Email');

        return $loader;
    }
}
