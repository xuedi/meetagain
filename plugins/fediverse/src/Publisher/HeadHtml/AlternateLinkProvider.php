<?php declare(strict_types=1);

namespace Plugin\Fediverse\Publisher\HeadHtml;

use App\Publisher\HeadHtml\HeadHtmlProviderInterface;
use Override;
use Plugin\Fediverse\ActivityPub\EventObject;
use Plugin\Fediverse\Entity\Publication;
use Plugin\Fediverse\Repository\PublicationRepository;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class AlternateLinkProvider implements HeadHtmlProviderInterface
{
    private const string EVENT_ROUTE = 'app_event_details';

    public function __construct(
        private RequestStack $requestStack,
        private EventObject $eventObject,
        private PublicationRepository $publications,
    ) {}

    #[Override]
    public function getHeadHtml(): ?string
    {
        $request = $this->requestStack->getMainRequest();
        $eventId = $request?->attributes->get('id');
        if ($request?->attributes->get('_route') !== self::EVENT_ROUTE || !is_numeric($eventId)) {
            return null;
        }

        $publication = $this->publications->findLiveForEvent((int) $eventId);
        if (!$publication instanceof Publication) {
            return null;
        }

        return sprintf('<link rel="alternate" type="application/activity+json" href="%s">', htmlspecialchars(
            $this->eventObject->id($publication),
            ENT_QUOTES | ENT_HTML5,
        ));
    }
}
