<?php declare(strict_types=1);

namespace App\EventSubscriber\Security;

use App\Controller\SecurityController;
use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class MalformedLoginSubscriber implements EventSubscriberInterface
{
    private const string USERNAME_PARAMETER = '_username';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onKernelRequest', 9],
            ],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isMethod('POST')) {
            return;
        }

        if ($request->attributes->get('_route') !== SecurityController::LOGIN_ROUTE) {
            return;
        }

        if (is_string($request->request->all()[self::USERNAME_PARAMETER] ?? null)) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate(SecurityController::LOGIN_ROUTE)));
    }
}
