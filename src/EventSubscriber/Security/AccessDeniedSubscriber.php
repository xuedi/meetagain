<?php declare(strict_types=1);

namespace App\EventSubscriber\Security;

use App\Service\Security\AccessDeniedLogger;
use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Logs every kernel-level access-denied turn into a 403, durable for the admin UI.
 *
 * Runs at priority 16, below NotFoundSubscriber (32) so 404s never reach this code path,
 * above the firewall's own access-denied handler so the response is left untouched.
 *
 * The existing per-controller Monolog warnings (e.g. CmsController::logAccessDenied) stay -
 * Monolog feeds operators/BugSink, this table feeds the admin Security tab. They are
 * complementary, not redundant.
 */
readonly class AccessDeniedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AccessDeniedLogger $accessDeniedLogger,
    ) {}

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => [
                ['onKernelException', 16],
            ],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $isHttpAccessDenied = $exception instanceof AccessDeniedHttpException;
        $isCoreAccessDenied = $exception instanceof AccessDeniedException;

        if (!$isHttpAccessDenied && !$isCoreAccessDenied) {
            return;
        }

        $this->accessDeniedLogger->log($exception, $event->getRequest(), $isHttpAccessDenied);
    }
}
