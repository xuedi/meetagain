<?php declare(strict_types=1);

namespace App\EventSubscriber\Security;

use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class CrossOriginWriteSubscriber implements EventSubscriberInterface
{
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];

    public function __construct(
        private LoggerInterface $logger,
    ) {}

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['onKernelController', 0],
            ],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return;
        }

        if (!$this->isAdminPath($request)) {
            return;
        }

        if ($this->isSameOrigin($request)) {
            return;
        }

        $this->logger->warning('Cross-origin write to the admin area refused', [
            'path' => $request->getPathInfo(),
            'origin' => $request->headers->get('Origin'),
            'fetchSite' => $request->headers->get('Sec-Fetch-Site'),
        ]);

        throw new AccessDeniedHttpException('This request did not come from the admin area itself.');
    }

    private function isAdminPath(Request $request): bool
    {
        $path = $request->getPathInfo();

        $locale = $request->attributes->get('_locale');
        if (is_string($locale) && $locale !== '' && str_starts_with($path, '/' . $locale . '/')) {
            $path = substr($path, strlen($locale) + 1);
        }

        return $path === '/admin' || str_starts_with($path, '/admin/');
    }

    private function isSameOrigin(Request $request): bool
    {
        $origin = $request->headers->get('Origin');
        if ($origin !== null && $origin !== '') {
            return hash_equals($request->getSchemeAndHttpHost(), $origin);
        }

        $fetchSite = $request->headers->get('Sec-Fetch-Site');
        if ($fetchSite !== null && $fetchSite !== '') {
            return $fetchSite === 'same-origin' || $fetchSite === 'none';
        }

        return true;
    }
}
