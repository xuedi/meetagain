<?php declare(strict_types=1);

namespace Module\Email\Internal;

use App\Service\Config\ConfigService;
use Module\Email\Contract\SendingIdentity;
use Module\Email\Internal\Entity\EmailQueue;
use Psr\Log\LoggerInterface;
use Throwable;
use Twig\Environment;

readonly class LayoutRenderer
{
    public const string CONTEXT_KEY = '_layout';

    private const string TEMPLATE = '@Email/layout.html.twig';
    private const string DEFAULT_ACCENT = '#2f6fd0';

    public function __construct(
        private Environment $twig,
        private ConfigService $configService,
        private LoggerInterface $logger,
    ) {}

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
            'accent' => $colors['color_link'] ?? $colors['color_primary'] ?? self::DEFAULT_ACCENT,
            'links' => $identity->links,
        ];

        if ($identity->attribution !== null) {
            $snapshot['attribution'] = $identity->attribution;
        }

        return $snapshot;
    }

    public function wrap(EmailQueue $mail): string
    {
        $layout = $mail->getContext()[self::CONTEXT_KEY] ?? null;
        if (!is_array($layout)) {
            $this->logger->warning('Email row carries no frozen layout, sending the bare body', [
                'email_queue_id' => $mail->getId(),
                'template' => $mail->getTemplate(),
            ]);

            return $mail->getRenderedBody() ?? '';
        }

        return $this->render($mail, $layout) ?? $mail->getRenderedBody() ?? '';
    }

    /** @param array<string, mixed> $context */
    private function render(EmailQueue $mail, array $context): ?string
    {
        try {
            return $this->twig->render(self::TEMPLATE, [
                ...$context,
                'locale' => $mail->getLang() ?? 'en',
                'subject' => $mail->getSubject() ?? '',
                'body' => $mail->getRenderedBody() ?? '',
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Email layout rendering failed, sending the bare body', [
                'email_queue_id' => $mail->getId(),
                'template' => $mail->getTemplate(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
