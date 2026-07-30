<?php declare(strict_types=1);

namespace App\Publisher\CanonicalUrl;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Service\Seo\EventCanonicalResolver;
use App\Service\Seo\UrlOwnerService;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final readonly class EventCanonicalUrlProvider implements CanonicalUrlProviderInterface
{
    public function __construct(
        private EventRepository $eventRepository,
        private EventCanonicalResolver $resolver,
        private UrlOwnerService $urlOwnerService,
        private RouterInterface $router,
    ) {}

    #[Override]
    public function getCanonicalUrl(string $currentUrl, Request $request): ?string
    {
        if ($request->attributes->get('_route') !== 'app_event_details') {
            return null;
        }

        $eventId = (int) $request->attributes->get('id');
        if ($eventId <= 0) {
            return null;
        }

        $event = $this->eventRepository->find($eventId);
        if (!$event instanceof Event) {
            return null;
        }

        $root = $this->resolver->resolveRoot($event, $request->getLocale());
        if ($root->getId() === $event->getId()) {
            return null;
        }

        $path = $this->router->generate(
            'app_event_details',
            ['_locale' => $request->getLocale(), 'id' => $root->getId()],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );

        // The owning host, never the request host - an alternate host must not be able to declare
        // itself canonical for events.
        return $this->urlOwnerService->getOwnerHost('app_event_details', ['id' => $root->getId()]) . $path;
    }
}
