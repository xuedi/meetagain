<?php declare(strict_types=1);

namespace App\EventSubscriber\Security;

use App\Service\Security\NotFoundLogger;
use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class NotFoundSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private NotFoundLogger $notFoundLogger,
    ) {}

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => [
                ['onKernelException', 32],
            ],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->getThrowable() instanceof NotFoundHttpException) {
            return;
        }

        $this->notFoundLogger->log($event->getRequest());
    }
}
