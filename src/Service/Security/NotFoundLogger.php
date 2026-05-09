<?php declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\NotFoundLog;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

readonly class NotFoundLogger
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    public function log(Request $request): void
    {
        $log = new NotFoundLog();
        $log->setCreatedAt(new DateTimeImmutable());
        $log->setIp($request->getClientIp() ?? '');
        $log->setUrl($request->getPathInfo());
        $log->setUserAgent($request->headers->get('User-Agent'));
        $log->setReferer($request->headers->get('Referer'));

        try {
            $this->em->persist($log);
            $this->em->flush();
        } catch (Throwable $e) {
            $this->logger->warning('Failed to persist not-found log: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}
