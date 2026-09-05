<?php declare(strict_types=1);

namespace Plugin\Photos\Member;

use App\Entity\User;
use App\Item\ListRegistry;
use App\Service\Member\ViewSectionProviderInterface;
use Override;
use Plugin\Photos\Service\ConfigService;
use Plugin\Photos\Service\PhotoService;
use Twig\Environment;

readonly class StreamSectionProvider implements ViewSectionProviderInterface
{
    private const int STRIP_LENGTH = 8;

    public function __construct(
        private PhotoService $photoService,
        private ConfigService $configService,
        private ListRegistry $listRegistry,
        private Environment $twig,
    ) {}

    #[Override]
    public function renderSection(User $viewer, User $target): ?string
    {
        if (!$this->listRegistry->has(PhotoService::ITEM_TYPE) || !$this->configService->getConfig()->isMemberStreams()) {
            return null;
        }

        $targetId = $target->getId();
        if ($targetId === null) {
            return null;
        }

        $photos = $this->photoService->getStream($targetId, self::STRIP_LENGTH);
        if ($photos === []) {
            return null;
        }

        return $this->twig->render('@Photos/member/_stream_section.html.twig', [
            'photos' => $photos,
            'target' => $target,
        ]);
    }
}
