<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\AccessDeniedLog;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Throwable;

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
        private EntityManagerInterface $em,
        private Security $security,
        private LoggerInterface $logger,
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

        $request = $event->getRequest();
        $log = new AccessDeniedLog();
        $log->setCreatedAt(new DateTimeImmutable());
        $log->setIp($request->getClientIp() ?? '');
        $log->setUrl($request->getRequestUri());
        $log->setReason($this->resolveReason($exception, $isHttpAccessDenied));
        $log->setUserAgent($request->headers->get('User-Agent'));

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $log->setUser($user);
        }

        try {
            $this->em->persist($log);
            $this->em->flush();
        } catch (Throwable $e) {
            $this->logger->warning('Failed to persist access-denied log: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    private function resolveReason(Throwable $exception, bool $isHttpAccessDenied): string
    {
        $message = $exception->getMessage();
        if (str_starts_with($message, 'Invalid CSRF')) {
            return 'csrf';
        }
        if ($isHttpAccessDenied) {
            return 'controller';
        }
        $previous = $exception->getPrevious();
        if ($previous !== null && str_contains($previous->getMessage(), 'voter')) {
            return 'voter';
        }
        if (str_contains($message, 'voter') || str_contains($message, 'Access Denied by')) {
            return 'voter';
        }

        return 'firewall';
    }
}
