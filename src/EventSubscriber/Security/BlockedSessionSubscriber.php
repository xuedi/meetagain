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
use Twig\Environment;

readonly class BlockedSessionSubscriber implements EventSubscriberInterface
{
    /** @var list<string> */
    private const array EXCLUDED_PATH_PREFIXES = [
        '/admin/security/',
        '/security/blocked',
    ];

    public function __construct(
        private BlockedSessionStore $blockStore,
        private Environment $twig,
        private LoggerInterface $logger,
        private string $environment,
    ) {}

    #[Override]
    public static function getSubscribedEvents(): array
    {
        // Priority 64: after SessionListener (128) so the session factory is
        // attached to the Request before we set a 403 response. Otherwise the
        // dev-mode WebDebugToolbar (kernel.response) injects a Twig toolbar
        // whose globals call $request->getSession() and crash with
        // SessionNotFoundException, mangling our 403 into a 400.
        // Still earlier than Firewall (8) so blocked traffic never hits auth.
        return [
            KernelEvents::REQUEST => [
                ['onKernelRequest', 64],
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
        $sessionId = $this->readSessionCookie($request);
        if ($sessionId !== null) {
            return $sessionId;
        }

        return 'ip:' . ($ip !== '' ? $ip : 'unknown');
    }

    private function readSessionCookie(Request $request): ?string
    {
        try {
            $session = $request->getSession();
            $cookieValue = $request->cookies->get($session->getName());
            return is_string($cookieValue) && $cookieValue !== '' ? $cookieValue : null;
        } catch (Throwable $e) {
            $this->logger->debug('Session read failed in BlockedSessionSubscriber: ' . $e->getMessage());
            return null;
        }
    }

    private function buildBlockResponse(): Response
    {
        try {
            $html = $this->twig->render('security/blocked.html.twig');
        } catch (Throwable $e) {
            $this->logger->error('Failed to render blocked page template: ' . $e->getMessage());
            $html = '<!DOCTYPE html><html><body><h1>Temporarily blocked</h1></body></html>';
        }

        return new Response($html, 403, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
