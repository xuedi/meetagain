<?php declare(strict_types=1);

namespace App\Service\Email;

use App\Entity\EmailQueue;
use App\Service\Cms\MenuService;
use App\Service\Config\ConfigService;
use App\Service\Config\SiteNameResolver;
use App\Service\Http\RequestHostResolver;
use App\Service\Media\SiteLogoResolver;
use Psr\Log\LoggerInterface;
use Throwable;
use Twig\Environment;

readonly class LayoutRenderer
{
    public const string CONTEXT_KEY = '_layout';

    private const string TEMPLATE = 'email/layout.html.twig';
    private const string LEGAL_MENU = 'col4';
    private const string DEFAULT_ACCENT = '#2f6fd0';

    public function __construct(
        private Environment $twig,
        private ConfigService $configService,
        private SiteNameResolver $siteNameResolver,
        private RequestHostResolver $hostResolver,
        private SiteLogoResolver $logoResolver,
        private MenuService $menuService,
        private InlineLogoFactory $inlineLogoFactory,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function capture(string $locale): array
    {
        $logo = $this->logoResolver->resolveAbsolute();
        $colors = $this->configService->getThemeColors();

        return [
            'siteName' => $this->siteNameResolver->resolve(),
            'siteUrl' => rtrim($this->hostResolver->getSchemeAndHost(), '/'),
            'logoUrl' => $logo['url'],
            'logoHeight' => $logo['height'],
            'logoImageId' => $logo['imageId'],
            'accent' => $colors['color_link'] ?? $colors['color_primary'] ?? self::DEFAULT_ACCENT,
            'links' => $this->legalLinks($locale),
        ];
    }

    public function wrap(EmailQueue $mail): RenderedLayout
    {
        $locale = $mail->getLang() ?? 'en';
        $context = $mail->getContext()[self::CONTEXT_KEY] ?? null;
        if (!is_array($context)) {
            $context = $this->capture($locale);
        }

        $inlineLogo = $this->inlineLogoFactory->create($context['logoImageId'] ?? null);

        try {
            $html = $this->twig->render(self::TEMPLATE, [
                ...$context,
                'locale' => $locale,
                'subject' => $mail->getSubject() ?? '',
                'body' => $mail->getRenderedBody() ?? '',
                'logoCid' => $inlineLogo !== null ? InlineLogoFactory::CID_NAME : null,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Email layout rendering failed, sending the bare body', [
                'email_queue_id' => $mail->getId(),
                'template' => $mail->getTemplate(),
                'error' => $e->getMessage(),
            ]);

            return new RenderedLayout($mail->getRenderedBody() ?? '');
        }

        return new RenderedLayout($html, $inlineLogo);
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private function legalLinks(string $locale): array
    {
        $host = rtrim($this->hostResolver->getSchemeAndHost(), '/');

        $links = [];
        foreach ($this->menuService->getMenuForContext(self::LEGAL_MENU, null, $locale) as $item) {
            $links[] = ['label' => $item->name, 'url' => $host . '/' . ltrim($item->slug, '/')];
        }

        return $links;
    }
}
