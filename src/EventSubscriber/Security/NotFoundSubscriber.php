<?php declare(strict_types=1);

namespace App\EventSubscriber\Security;

use App\Enum\SecurityEventType;
use App\Service\Security\SecurityService;
use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class NotFoundSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SecurityService $securityService,
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

        $this->securityService->event(SecurityEventType::NotFound, $event->getRequest());
    }
}
