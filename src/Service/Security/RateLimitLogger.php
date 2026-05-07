<?php declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\RateLimitLog;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

readonly class RateLimitLogger
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    public function log(string $limiter, Request $request, ?string $userIdentifier = null): void
    {
        $log = new RateLimitLog();
        $log->setCreatedAt(new DateTimeImmutable());
        $log->setIp($request->getClientIp() ?? '');
        $log->setUrl($request->getRequestUri());
        $log->setLimiter($limiter);
        $log->setUserAgent($request->headers->get('User-Agent'));
        if ($userIdentifier !== null && $userIdentifier !== '') {
            $log->setUserIdentifier($userIdentifier);
        }

        try {
            $this->em->persist($log);
            $this->em->flush();
        } catch (Throwable $e) {
            $this->logger->warning('Failed to persist rate-limit log: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}
