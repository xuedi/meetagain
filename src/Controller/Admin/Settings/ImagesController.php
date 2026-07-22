<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\Actions\AdminTopActionForm;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use App\Repository\ImageRepository;
use App\Service\Config\LanguageService;
use App\Service\Media\AltLocaleRequirementResolver;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use App\Service\Media\ImageTypes\ImageTypeRegistry;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system/images')]
final class ImagesController extends AbstractSettingsController implements AdminNavigationInterface, AdminTabsInterface
{
    private const string DEFAULT_RANGE = 'all';

    /** @var array<string, string|null> */
    private const array RANGE_OFFSETS = [
        '24h' => '-24 hours',
        '1w' => '-1 week',
        '1m' => '-1 month',
        '1y' => '-1 year',
        'all' => null,
    ];

    public function __construct(
        TranslatorInterface $translator,
        private readonly ImageService $imageService,
        private readonly ImageRepository $imageRepository,
        private readonly ImageLocationRepository $imageLocationRepository,
        private readonly ImageLocationService $imageLocationService,
        private readonly ImageTypeRegistry $imageTypeRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly LanguageService $languageService,
        private readonly AltLocaleRequirementResolver $altLocaleRequirementResolver,
    ) {
        parent::__construct($translator, 'images');
    }

    #[Route('', name: 'app_admin_system_images', methods: ['GET'])]
    public function images(Request $request): Response
    {
        $range = $request->query->getString('range', self::DEFAULT_RANGE);
        if (!array_key_exists($range, self::RANGE_OFFSETS)) {
            $range = self::DEFAULT_RANGE;
        }
        $rangeOffset = self::RANGE_OFFSETS[$range];
        $since = $rangeOffset !== null ? new DateTimeImmutable($rangeOffset) : null;

        $locationParam = $request->query->getString('location');
        $locationFilter = $this->resolveLocation($locationParam);

        $totalCount = $this->imageRepository->countFiltered(null, null);
        $filteredCount = $this->imageRepository->countFiltered($locationFilter, $since);
        $images = $this->imageRepository->findFiltered($locationFilter, $since);
        $usageCounts = $this->imageLocationRepository->countPerImageId();

        $info = [
            new AdminTopInfoHtml(sprintf('<strong>%d</strong>&nbsp;%s', $totalCount, $this->translator->trans('admin_system_images.summary_total'))),
        ];
        if ($since !== null || $locationFilter !== null) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $filteredCount,
                $this->translator->trans('admin_system_images.summary_in_range'),
            ));
        }

        $adminTop = new AdminTop(info: $info, actions: [
            new AdminTopActionForm(
                label: $this->translator->trans('admin_system_images.button_regenerate_thumbnails'),
                target: $this->generateUrl('app_admin_regenerate_thumbnails'),
                csrfTokenId: 'admin_regenerate_thumbnails',
                icon: 'sync',
                variant: 'is-warning',
            ),
            new AdminTopActionForm(
                label: $this->translator->trans('admin_system_images.button_cleanup_thumbnails'),
                target: $this->generateUrl('app_admin_cleanup_thumbnails'),
                csrfTokenId: 'admin_cleanup_thumbnails',
                icon: 'trash',
                variant: 'is-warning',
            ),
            new AdminTopActionForm(
                label: $this->translator->trans('admin_system_images.button_sync_locations'),
                target: $this->generateUrl('app_admin_sync_image_locations'),
                csrfTokenId: 'admin_sync_image_locations',
                icon: 'map-pin',
                variant: 'is-warning',
            ),
            $this->buildLocationDropdown($locationFilter, $range, $since),
            $this->buildRangeDropdown($range, $locationFilter),
        ]);

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

        $adminTop = new AdminTop(info: [
            new AdminTopInfoHtml(sprintf('<strong>#%d</strong>', $image->getId())),
            new AdminTopInfoHtml(sprintf('<span class="tag is-light">%s</span>', htmlspecialchars($image->getType()->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
        ], actions: [
            new AdminTopActionButton(
                label: $this->translator->trans('global.button_back'),
                target: $this->generateUrl('app_admin_system_images'),
                icon: 'arrow-left',
            ),
        ]);

        return $this->render('admin/system/images/show.html.twig', [
            'active' => 'system',
            'image' => $image,
            'locations' => $locations,
            'editLinks' => $editLinks,
            'previewSize' => $this->imageTypeRegistry->getAdminPreviewSize($image->getType()),
            'sourceLocale' => $this->languageService->getFilteredDefaultLocale(),
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/{id}/alt', name: 'app_admin_system_images_update_alt', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateAlt(int $id, Request $request): Response
    {
        $image = $this->imageRepository->find($id);
        if ($image === null) {
            throw $this->createNotFoundException('Image not found');
        }

        if (!$this->isCsrfTokenValid('image_update_alt_' . $id, (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_admin_system_images_show', ['id' => $id]);
        }

        $locale = (string) $request->request->get('locale', '');
        if (!in_array($locale, $this->altLocaleRequirementResolver->getRequiredAltLocales($image), true)) {
            return $this->redirectToRoute('app_admin_system_images_show', ['id' => $id]);
        }

        $alt = trim((string) $request->request->get('alt', ''));
        $alt = mb_substr($alt, 0, 255);

        if ($locale === $this->languageService->getFilteredDefaultLocale()) {
            $image->setAlt($alt === '' ? null : $alt);
        } else {
            $image->setAltTranslation($locale, $alt === '' ? null : $alt);
        }
        $image->setUpdatedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('admin_system_images.flash_alt_saved'));

        return $this->redirectToRoute('app_admin_system_images_show', ['id' => $id]);
    }

    #[Route('/{id}/attribution', name: 'app_admin_system_images_update_attribution', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateAttribution(int $id, Request $request): Response
    {
        $image = $this->imageRepository->find($id);
        if ($image === null) {
            throw $this->createNotFoundException('Image not found');
        }

        if (!$this->isCsrfTokenValid('image_update_attribution_' . $id, (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_admin_system_images_show', ['id' => $id]);
        }

        $attribution = trim((string) $request->request->get('attribution', ''));
        $attribution = mb_substr($attribution, 0, 500);

        $image->setAttribution($attribution === '' ? null : $attribution);
        $image->setAttributionNotRequired($request->request->getBoolean('attribution_not_required'));
        $image->setUpdatedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('admin_system_images.flash_attribution_saved'));

        return $this->redirectToRoute('app_admin_system_images_show', ['id' => $id]);
    }

    #[Route('/regenerate_thumbnails', name: 'app_admin_regenerate_thumbnails', methods: ['POST'])]
    public function regenerateThumbnails(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_regenerate_thumbnails', (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $startTime = microtime(true);
        $cnt = $this->imageService->regenerateAllThumbnails();
        $executionTime = round(microtime(true) - $startTime, 2);

        $this->addFlash('success', $this->translator->trans('admin_system_images.flash_thumbnails_regenerated', [
            '%count%' => $cnt,
            '%seconds%' => $executionTime,
        ]));

        return $this->redirectToRoute('app_admin_system_images');
    }

    #[Route('/cleanup_thumbnails', name: 'app_admin_cleanup_thumbnails', methods: ['POST'])]
    public function cleanupThumbnails(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_cleanup_thumbnails', (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $startTime = microtime(true);
        $cnt = $this->imageService->deleteObsoleteThumbnails();
        $executionTime = round(microtime(true) - $startTime, 2);

        $this->addFlash('success', $this->translator->trans('admin_system_images.flash_thumbnails_cleaned', [
            '%count%' => $cnt,
            '%seconds%' => $executionTime,
        ]));

        return $this->redirectToRoute('app_admin_system_images');
    }

    #[Route('/sync_locations', name: 'app_admin_sync_image_locations', methods: ['POST'])]
    public function syncLocations(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_sync_image_locations', (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->imageLocationService->discover();
        $this->addFlash('success', $this->translator->trans('admin_system_images.flash_locations_synced'));

        return $this->redirectToRoute('app_admin_system_images');
    }

    private function resolveLocation(string $param): ?ImageType
    {
        if ($param === '') {
            return null;
        }

        foreach (ImageType::cases() as $case) {
            if ($case->name === $param) {
                return $case;
            }
        }

        return null;
    }

    private function buildLocationDropdown(?ImageType $current, string $range, ?DateTimeImmutable $since): AdminTopActionDropdown
    {
        $rangeParam = $range === self::DEFAULT_RANGE ? [] : ['range' => $range];

        $options = [
            new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_system_images.location_filter_all'),
                target: $this->generateUrl('app_admin_system_images', $rangeParam),
                isActive: $current === null,
            ),
        ];
        foreach (ImageType::cases() as $case) {
            $params = ['location' => $case->name] + $rangeParam;
            $options[] = new AdminTopActionDropdownOption(
                label: $case->name,
                target: $this->generateUrl('app_admin_system_images', $params),
                isActive: $current === $case,
                count: $this->imageRepository->countFiltered($case, $since),
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_system_images.location_filter_label'),
                $current === null ? $this->translator->trans('admin_system_images.location_filter_all') : $current->name,
            ),
            options: $options,
            icon: 'map-pin',
        );
    }

    private function buildRangeDropdown(string $current, ?ImageType $location): AdminTopActionDropdown
    {
        $options = [];
        foreach (self::RANGE_OFFSETS as $key => $offset) {
            $params = $key === self::DEFAULT_RANGE ? [] : ['range' => $key];
            if ($location !== null) {
                $params['location'] = $location->name;
            }
            $optionSince = $offset !== null ? new DateTimeImmutable($offset) : null;
            $options[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_system_images.range_' . $key),
                target: $this->generateUrl('app_admin_system_images', $params),
                isActive: $key === $current,
                count: $key === self::DEFAULT_RANGE ? null : $this->imageRepository->countFiltered($location, $optionSince),
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_system_images.range_filter_label'),
                $this->translator->trans('admin_system_images.range_' . $current),
            ),
            options: $options,
            icon: 'clock',
        );
    }
}
