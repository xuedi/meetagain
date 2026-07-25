<?php declare(strict_types=1);

namespace App\Publisher\AlternateLinks;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Service\Config\ConfigService;
use App\Service\Seo\EventCanonicalResolver;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * A canonical target must appear in the hreflang cluster it is listed in, otherwise search
 * engines discard the cluster - so an event page advertises the roots of the other locales,
 * not its own URLs. The locales of one series can resolve to different events.
 */
final readonly class EventAlternateLinkProvider implements AlternateLinkProviderInterface
{
    public function __construct(
        private EventRepository $eventRepository,
        private EventCanonicalResolver $resolver,
        private ConfigService $configService,
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

        $host = rtrim($this->configService->getHost(), '/');
        $rootUrls = [];
        foreach (array_keys($localeUrls) as $locale) {
            $rootId = $this->resolver->resolveRoot($event, $locale)->getId();
            $rootUrls[$locale] = $host . $this->router->generate(
                'app_event_details',
                ['_locale' => $locale, 'id' => $rootId],
                UrlGeneratorInterface::ABSOLUTE_PATH,
            );
        }

        return $rootUrls;
    }
}
