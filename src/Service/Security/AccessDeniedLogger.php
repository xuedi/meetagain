<?php declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\AccessDeniedLog;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

readonly class AccessDeniedLogger
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private LoggerInterface $logger,
    ) {}

    public function log(Throwable $exception, Request $request, bool $isHttpAccessDenied): void
    {
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
