<?php declare(strict_types=1);

namespace App\Publisher\AlternateLinks;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Service\Seo\EventCanonicalResolver;
use App\Service\Seo\UrlOwnerService;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final readonly class EventAlternateLinkProvider implements AlternateLinkProviderInterface
{
    public function __construct(
        private EventRepository $eventRepository,
        private EventCanonicalResolver $resolver,
        private UrlOwnerService $urlOwnerService,
        private RouterInterface $router,
    ) {}

    #[Override]
    public function getAlternateLinks(array $localeUrls, Request $request): ?array
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

        // Roots, not the event's own URLs: a canonical target absent from its cluster voids the cluster
        $hostByRootId = [];
        $rootUrls = [];
        foreach (array_keys($localeUrls) as $locale) {
            $rootId = $this->resolver->resolveRoot($event, $locale)->getId();
            $hostByRootId[$rootId] ??= $this->urlOwnerService->getOwnerHost('app_event_details', ['id' => $rootId]);
            $rootUrls[$locale] =
                $hostByRootId[$rootId]
                . $this->router->generate('app_event_details', ['_locale' => $locale, 'id' => $rootId], UrlGeneratorInterface::ABSOLUTE_PATH);
        }

        return $rootUrls;
    }
}
