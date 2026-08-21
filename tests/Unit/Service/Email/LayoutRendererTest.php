<?php declare(strict_types=1);

namespace Tests\Unit\Service\Email;

use App\Entity\EmailQueue;
use App\Service\Cms\MenuItem;
use App\Service\Cms\MenuService;
use App\Service\Config\ConfigService;
use App\Service\Config\SiteNameResolver;
use App\Service\Email\InlineLogoFactory;
use App\Service\Email\LayoutRenderer;
use App\Service\Http\RequestHostResolver;
use App\Service\Media\SiteLogoResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Mime\Part\DataPart;
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
        $html = $this->renderer()->wrap($mail)->html;

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
        $html = $this->renderer()->wrap($mail)->html;

        // Assert
        static::assertStringContainsString('https://example.org/images/thumbnails/hash_h120.webp', $html);
        static::assertStringContainsString('alt="Example Site"', $html);
        static::assertStringContainsString('https://example.org/en/imprint', $html);
    }

    public function testAnEmbeddedLogoIsReferencedByContentIdInsteadOfByUrl(): void
    {
        // Arrange
        $inlineLogoFactory = $this->createStub(InlineLogoFactory::class);
        $inlineLogoFactory->method('create')->willReturn(new DataPart('png-bytes', InlineLogoFactory::CID_NAME, 'image/png')->asInline());
        $mail = $this->queued('<p>body</p>');

        // Act
        $rendered = $this->renderer(inlineLogoFactory: $inlineLogoFactory)->wrap($mail);

        // Assert
        static::assertStringContainsString('src="cid:' . InlineLogoFactory::CID_NAME . '"', $rendered->html);
        static::assertNotNull($rendered->inlineLogo);
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
        $html = $this->renderer()->wrap($mail)->html;

        // Assert
        static::assertStringStartsWith('<!DOCTYPE html>', $html);
        static::assertStringContainsString('</body>', $html);
        static::assertStringEndsWith('</html>', trim($html));
    }

    public function testARowWithoutAStoredSnapshotFallsBackToLiveResolution(): void
    {
        // Arrange
        $mail = new EmailQueue()
            ->setSubject('Subject')
            ->setLang('en')
            ->setContext([])
            ->setRenderedBody('<p>legacy row</p>');

        // Act
        $html = $this->renderer()->wrap($mail)->html;

        // Assert
        static::assertStringContainsString('<p>legacy row</p>', $html);
        static::assertStringContainsString('Example Site', $html);
    }

    public function testABrokenLayoutTemplateFallsBackToTheBareBody(): void
    {
        // Arrange
        $twig = new Environment(new ArrayLoader(['email/layout.html.twig' => "{{ include('does-not-exist.html.twig') }}"]));
        $twig->addExtension(new TranslationExtension(new Translator('en')));
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('error');
        $mail = $this->queued('<p>body</p>');

        // Act
        $rendered = $this->renderer(twig: $twig, logger: $loggerMock)->wrap($mail);

        // Assert
        static::assertSame('<p>body</p>', $rendered->html);
    }

    public function testCaptureSnapshotsTheSendingSite(): void
    {
        // Act
        $snapshot = $this->renderer()->capture('en');

        // Assert
        static::assertSame('Example Site', $snapshot['siteName']);
        static::assertSame('https://example.org', $snapshot['siteUrl']);
        static::assertSame('https://example.org/images/thumbnails/hash_h120.webp', $snapshot['logoUrl']);
        static::assertSame('#123456', $snapshot['accent']);
        static::assertSame([['label' => 'Imprint', 'url' => 'https://example.org/en/imprint']], $snapshot['links']);
    }

    private function queued(?string $body): EmailQueue
    {
        return new EmailQueue()
            ->setSubject('Subject')
            ->setLang('en')
            ->setRenderedBody($body)
            ->setContext([
                LayoutRenderer::CONTEXT_KEY => [
                    'siteName' => 'Example Site',
                    'siteUrl' => 'https://example.org',
                    'logoUrl' => 'https://example.org/images/thumbnails/hash_h120.webp',
                    'logoHeight' => 120,
                    'logoImageId' => 7,
                    'accent' => '#123456',
                    'links' => [['label' => 'Imprint', 'url' => 'https://example.org/en/imprint']],
                ],
            ]);
    }

    private function renderer(
        ?Environment $twig = null,
        ?InlineLogoFactory $inlineLogoFactory = null,
        ?LoggerInterface $logger = null,
    ): LayoutRenderer {
        if ($twig === null) {
            $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 4) . '/templates'));
            $twig->addExtension(new TranslationExtension(new Translator('en')));
        }

        $configService = $this->createStub(ConfigService::class);
        $configService->method('getThemeColors')->willReturn(['color_link' => '#123456']);

        $siteNameResolver = $this->createStub(SiteNameResolver::class);
        $siteNameResolver->method('resolve')->willReturn('Example Site');

        $hostResolver = $this->createStub(RequestHostResolver::class);
        $hostResolver->method('getSchemeAndHost')->willReturn('https://example.org');

        $logoResolver = $this->createStub(SiteLogoResolver::class);
        $logoResolver->method('resolveAbsolute')->willReturn([
            'url' => 'https://example.org/images/thumbnails/hash_h120.webp',
            'height' => 120,
            'imageId' => 7,
        ]);

        $menuService = $this->createStub(MenuService::class);
        $menuService->method('getMenuForContext')->willReturn([new MenuItem('/en/imprint', 'Imprint', 0.0)]);

        if ($inlineLogoFactory === null) {
            $inlineLogoFactory = $this->createStub(InlineLogoFactory::class);
            $inlineLogoFactory->method('create')->willReturn(null);
        }

        return new LayoutRenderer(
            twig: $twig,
            configService: $configService,
            siteNameResolver: $siteNameResolver,
            hostResolver: $hostResolver,
            logoResolver: $logoResolver,
            menuService: $menuService,
            inlineLogoFactory: $inlineLogoFactory,
            logger: $logger ?? $this->createStub(LoggerInterface::class),
        );
    }
}
