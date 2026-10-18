<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\Config\PluginService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class PluginRouteGuard
{
    public function __construct(
        private PluginService $pluginService,
    ) {}

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 30)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $plugin = $event->getRequest()->attributes->get('_plugin');
        if (is_string($plugin) && !$this->pluginService->isEnabled($plugin)) {
            throw new NotFoundHttpException(sprintf('The %s plugin is switched off.', $plugin));
        }
    }
}
