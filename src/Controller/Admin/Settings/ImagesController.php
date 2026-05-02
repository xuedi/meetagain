<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Repository\ImageLocationRepository;
use App\Repository\ImageRepository;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system/images')]
final class ImagesController extends AbstractSettingsController implements AdminNavigationInterface, AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly ImageService $imageService,
        private readonly ImageRepository $imageRepository,
        private readonly ImageLocationRepository $imageLocationRepository,
        private readonly ImageLocationService $imageLocationService,
    ) {
        parent::__construct($translator, 'images');
    }

    #[Route('', name: 'app_admin_system_images', methods: ['GET'])]
    public function images(): Response
    {
        $images = $this->imageRepository->findAll();
        $usageCounts = $this->imageLocationRepository->countPerImageId();

        $adminTop = new AdminTop(
            info: [
                new AdminTopInfoHtml(sprintf(
                    '<strong>%d</strong>&nbsp;%s',
                    count($images),
                    $this->translator->trans('admin_system_images.summary_total'),
                )),
            ],
            actions: [
                new AdminTopActionButton(
                    label: $this->translator->trans('admin_system_images.button_regenerate_thumbnails'),
                    target: $this->generateUrl('app_admin_regenerate_thumbnails'),
                    icon: 'sync',
                    variant: 'is-warning',
                    confirm: $this->translator->trans('admin_system_images.confirm_regenerate_thumbnails'),
                ),
                new AdminTopActionButton(
                    label: $this->translator->trans('admin_system_images.button_cleanup_thumbnails'),
                    target: $this->generateUrl('app_admin_cleanup_thumbnails'),
                    icon: 'trash',
                    variant: 'is-warning',
                    confirm: $this->translator->trans('admin_system_images.confirm_cleanup_thumbnails'),
                ),
                new AdminTopActionButton(
                    label: $this->translator->trans('admin_system_images.button_sync_locations'),
                    target: $this->generateUrl('app_admin_sync_image_locations'),
                    icon: 'map-pin',
                    variant: 'is-warning',
                    confirm: $this->translator->trans('admin_system_images.confirm_sync_locations'),
                ),
            ],
        );

        return $this->render('admin/system/images/index.html.twig', [
            'active' => 'system',
            'images' => $images,
            'usageCounts' => $usageCounts,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_system_images_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $image = $this->imageRepository->find($id);
        if ($image === null) {
            throw $this->createNotFoundException('Image not found');
        }

        $locations = $this->imageLocationRepository->findByImageId($id);

        $editLinks = [];
        foreach ($locations as $location) {
            $editLinks[$location->getId()] = $this->imageLocationService->resolveEditLink($location);
        }

        $adminTop = new AdminTop(
            info: [
                new AdminTopInfoHtml(sprintf(
                    '<strong>#%d</strong>',
                    $image->getId(),
                )),
                new AdminTopInfoHtml(sprintf(
                    '<span class="tag is-light">%s</span>',
                    htmlspecialchars($image->getType()->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                )),
            ],
            actions: [
                new AdminTopActionButton(
                    label: $this->translator->trans('global.button_back'),
                    target: $this->generateUrl('app_admin_system_images'),
                    icon: 'arrow-left',
                ),
            ],
        );

        return $this->render('admin/system/images/show.html.twig', [
            'active' => 'system',
            'image' => $image,
            'locations' => $locations,
            'editLinks' => $editLinks,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/regenerate_thumbnails', name: 'app_admin_regenerate_thumbnails', methods: ['GET'])]
    public function regenerateThumbnails(): Response
    {
        $startTime = microtime(true);
        $cnt = $this->imageService->regenerateAllThumbnails();
        $executionTime = round(microtime(true) - $startTime, 2);

        $this->addFlash('success', $this->translator->trans('admin_system_images.flash_thumbnails_regenerated', [
            '%count%' => $cnt,
            '%seconds%' => $executionTime,
        ]));

        return $this->redirectToRoute('app_admin_system_images');
    }

    #[Route('/cleanup_thumbnails', name: 'app_admin_cleanup_thumbnails', methods: ['GET'])]
    public function cleanupThumbnails(): Response
    {
        $startTime = microtime(true);
        $cnt = $this->imageService->deleteObsoleteThumbnails();
        $executionTime = round(microtime(true) - $startTime, 2);

        $this->addFlash('success', $this->translator->trans('admin_system_images.flash_thumbnails_cleaned', [
            '%count%' => $cnt,
            '%seconds%' => $executionTime,
        ]));

        return $this->redirectToRoute('app_admin_system_images');
    }

    #[Route('/sync_locations', name: 'app_admin_sync_image_locations', methods: ['GET'])]
    public function syncLocations(): Response
    {
        $this->imageLocationService->discover();
        $this->addFlash('success', $this->translator->trans('admin_system_images.flash_locations_synced'));

        return $this->redirectToRoute('app_admin_system_images');
    }
}
