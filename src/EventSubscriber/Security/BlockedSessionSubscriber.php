<?php declare(strict_types=1);

namespace App\EventSubscriber\Security;

use App\Service\Security\BlockedSessionStore;
use App\Service\Security\LoadtestBypass;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

readonly class BlockedSessionSubscriber implements EventSubscriberInterface
{
    /** @var list<string> */
    private const array EXCLUDED_PATH_PREFIXES = [
        '/admin/security/',
        '/security/blocked',
    ];

    /**
     * Self-contained block-page HTML. Inlined to avoid Twig globals (e.g.
     * AppVariable::getUser()) that touch the session - this listener fires at
     * REQUEST priority 240, before SessionListener has populated the session,
     * so any session-dependent rendering would crash with SessionNotFoundException.
     */
    private const string BLOCK_PAGE_HTML = <<<'HTML'
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Temporarily blocked</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f5f5f5; color: #222; }
                .card { max-width: 480px; padding: 2rem; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
                h1 { font-size: 1.4rem; margin-top: 0; }
                p { line-height: 1.5; color: #444; }
            </style>
        </head>
        <body>
            <div class="card">
                <h1>Temporarily blocked</h1>
                <p>Your session has been temporarily blocked due to suspicious activity. Please try again later.</p>
            </div>
        </body>
        </html>
        HTML;

    public function __construct(
        private BlockedSessionStore $blockStore,
        private LoggerInterface $logger,
        private string $environment,
    ) {}

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onKernelRequest', 240],
            ],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($this->isLoadtestBypass($request)) {
            return;
        }

        $path = $request->getPathInfo();
        foreach (self::EXCLUDED_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $ip = $request->getClientIp() ?? '';
        if ($ip !== '' && $this->blockStore->isIpBlocked($ip)) {
            $event->setResponse($this->buildBlockResponse());
            $event->stopPropagation();
            return;
        }

        $sessionId = $this->resolveSessionId($request, $ip);
        if ($this->blockStore->isSessionBlocked($sessionId)) {
            $event->setResponse($this->buildBlockResponse());
            $event->stopPropagation();
        }
    }

    private function isLoadtestBypass(Request $request): bool
    {
        return LoadtestBypass::isActive($request, $this->environment);
    }

    private function resolveSessionId(Request $request, string $ip): string
    {
        $sessionId = $this->safeReadSessionId($request);
        if ($sessionId !== null) {
            return $sessionId;
        }

        return 'ip:' . ($ip !== '' ? $ip : 'unknown');
    }

    private function safeReadSessionId(Request $request): ?string
    {
        try {
            if (!$request->hasSession(true)) {
                return null;
            }
            $session = $request->getSession();
            if (!$session->isStarted()) {
                return null;
            }
            $id = $session->getId();

            return $id !== '' ? $id : null;
        } catch (Throwable $e) {
            $this->logger->debug('Session read failed in BlockedSessionSubscriber: ' . $e->getMessage());
            return null;
        }
    }

    private function buildBlockResponse(): Response
    {
        return new Response(self::BLOCK_PAGE_HTML, 403, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
