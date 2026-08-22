<?php declare(strict_types=1);

namespace App\Service\Email;

use App\Emails\SendingIdentity;
use App\Entity\EmailQueue;
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
    private const string DEFAULT_ACCENT = '#2f6fd0';

    public function __construct(
        private Environment $twig,
        private ConfigService $configService,
        private SiteNameResolver $siteNameResolver,
        private RequestHostResolver $hostResolver,
        private SiteLogoResolver $logoResolver,
        private EmailFooterLinkResolver $footerLinks,
        private InlineLogoFactory $inlineLogoFactory,
        private LoggerInterface $logger,
    ) {}

    public function captureIdentity(string $locale): SendingIdentity
    {
        $logo = $this->logoResolver->resolveAbsolute();
        $siteName = $this->siteNameResolver->resolve();

        return new SendingIdentity(
            siteName: $siteName,
            siteUrl: rtrim($this->hostResolver->getSchemeAndHost(), '/'),
            logoUrl: $logo['url'],
            logoHeight: $logo['height'],
            logoImageId: $logo['imageId'],
            greeting: $siteName,
            links: $this->footerLinks->resolve($this->hostResolver->getSchemeAndHost(), $locale),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(SendingIdentity $identity): array
    {
        $colors = $this->configService->getThemeColors();

        $snapshot = [
            'siteName' => $identity->siteName,
            'siteUrl' => $identity->siteUrl,
            'logoUrl' => $identity->logoUrl,
            'logoHeight' => $identity->logoHeight,
            'logoImageId' => $identity->logoImageId,
            'accent' => $colors['color_link'] ?? $colors['color_primary'] ?? self::DEFAULT_ACCENT,
            'links' => $identity->links,
        ];

        if ($identity->attribution !== null) {
            $snapshot['attribution'] = $identity->attribution;
        }

        return $snapshot;
    }

    /**
     * @return array<string, mixed>
     */
    public function capture(string $locale): array
    {
        return $this->snapshot($this->captureIdentity($locale));
    }

    public function wrap(EmailQueue $mail): RenderedLayout
    {
        $locale = $mail->getLang() ?? 'en';
        $context = $mail->getContext()[self::CONTEXT_KEY] ?? null;
        if (!is_array($context)) {
            $this->logger->warning('Email row carries no frozen layout, resolving the sending identity at send time', [
                'email_queue_id' => $mail->getId(),
                'template' => $mail->getTemplate(),
            ]);
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
}
