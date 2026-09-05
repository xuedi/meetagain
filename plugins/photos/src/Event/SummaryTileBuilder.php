<?php declare(strict_types=1);

namespace Plugin\Photos\Event;

use App\Entity\Event;
use App\Item\Tag\FacetSelection;
use App\Item\Tag\FacetService;
use App\Service\Item\AssociationService;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Service\PhotoService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class SummaryTileBuilder
{
    public function __construct(
        private AssociationService $associations,
        private PhotoService $photoService,
        private DateTagService $dateTagService,
        private FacetService $facetService,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /** @return array{count: int, uploaders: list<int>, url: string}|null */
    public function build(Event $event): ?array
    {
        $uploaders = [];
        $count = 0;
        foreach ($this->associations->listForEvent((int) $event->getId()) as $association) {
            $photo = $association->getItemType() === PhotoService::ITEM_TYPE
                ? $this->photoService->getAttached((int) $association->getItemId())
                : null;

            if (!$photo instanceof Photo) {
                continue;
            }

            $count++;
            $uploaders[(int) $photo->getCreatedBy()] = true;
        }

        if ($count === 0) {
            return null;
        }

        return [
            'count' => $count,
            'uploaders' => array_keys($uploaders),
            'url' => $this->listUrl($event),
        ];
    }

    private function listUrl(Event $event): string
    {
        $base = $this->urlGenerator->generate('app_photos_photolist');
        $tag = $this->dateTagService->findDateTag($event);

        return $tag === null ? $base : $this->facetService->urlFor($base, new FacetSelection([(int) $tag->getId()]));
    }
}
