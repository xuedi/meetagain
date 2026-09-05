<?php declare(strict_types=1);

namespace Plugin\Photos\Event;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Service\Event\ImageBoxProviderInterface;
use App\Service\Item\AssociationService;
use Override;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Form\EventUploadType;
use Plugin\Photos\Service\ConfigService;
use Plugin\Photos\Service\PhotoService;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class ImageBoxProvider implements ImageBoxProviderInterface
{
    public function __construct(
        private ConfigService $configService,
        private PhotoService $photoService,
        private AssociationService $associations,
        private EventRepository $eventRepository,
        private FormFactoryInterface $formFactory,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'photos';
    }

    #[Override]
    public function renderImageBox(int $eventId): ?string
    {
        $event = $this->eventRepository->find($eventId);
        if (!$this->configService->getConfig()->isEventBox() || !$event instanceof Event) {
            return null;
        }

        return $this->twig->render('@Photos/event/image_box.html.twig', [
            'event' => $event,
            'photos' => $this->attached($eventId),
            'uploadForm' => $this->configService->canUpload() ? $this->uploadForm($eventId) : null,
        ]);
    }

    /** @return list<Photo> */
    private function attached(int $eventId): array
    {
        $photos = [];
        foreach ($this->associations->listForEvent($eventId) as $association) {
            $photo = $association->getItemType() === PhotoService::ITEM_TYPE
                ? $this->photoService->getAttached((int) $association->getItemId())
                : null;

            if ($photo instanceof Photo) {
                $photos[] = $photo;
            }
        }

        return $photos;
    }

    private function uploadForm(int $eventId): FormView
    {
        return $this->formFactory
            ->create(EventUploadType::class, null, [
                'action' => $this->urlGenerator->generate('app_plugin_photos_event_upload', ['id' => $eventId]),
            ])
            ->createView();
    }
}
