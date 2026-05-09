<?php declare(strict_types=1);

namespace App\EventSubscriber\Security;

use App\Service\Security\BlockedSessionStore;
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
        $body = $this->twig->render('security/blocked.html.twig');

        return new Response($body, 403);
    }
}
