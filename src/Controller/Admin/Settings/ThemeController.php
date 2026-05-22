<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Section\AdminCollapsibleSection;
use App\Admin\Section\Items\AdminSectionTextItem;
use App\Admin\Tabs\AdminTab;
use App\Admin\Tabs\AdminTabs;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\Form\SiteLogoType;
use App\Form\ThemeColorsType;
use App\Repository\ImageRepository;
use App\Service\Admin\CommandService;
use App\Service\Config\ConfigService;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
final class ThemeController extends AbstractSettingsController implements AdminNavigationInterface, AdminTabsInterface
{
    private const array CATEGORIES = ['general', 'admin', 'frontend', 'feedback'];

    /**
     * @var list<array{key: string, partial: string, category: string}>
     */
    private const array GALLERY_SECTIONS = [
        ['key' => 'typography', 'partial' => 'typography', 'category' => 'general'],
        ['key' => 'buttons', 'partial' => 'buttons', 'category' => 'general'],
        ['key' => 'boxes_columns', 'partial' => 'cards_boxes', 'category' => 'general'],
        ['key' => 'form_inputs', 'partial' => 'form_inputs', 'category' => 'general'],
        ['key' => 'form_select', 'partial' => 'form_select', 'category' => 'general'],
        ['key' => 'form_check', 'partial' => 'form_checkbox_radio', 'category' => 'general'],
        ['key' => 'form_textarea', 'partial' => 'form_textarea', 'category' => 'general'],
        ['key' => 'tooltips', 'partial' => 'tooltips_titles', 'category' => 'general'],
        ['key' => 'comp_image', 'partial' => 'comp_image', 'category' => 'general'],
        ['key' => 'comp_toggle', 'partial' => 'comp_toggle', 'category' => 'general'],
        ['key' => 'admin_top_infobar', 'partial' => 'admin_top_infobar', 'category' => 'admin'],
        ['key' => 'admin_list', 'partial' => 'admin_list', 'category' => 'admin'],
        ['key' => 'tags_status', 'partial' => 'tags_status', 'category' => 'admin'],
        ['key' => 'conventions', 'partial' => 'conventions', 'category' => 'admin'],
        ['key' => 'notifications', 'partial' => 'notifications_flash', 'category' => 'feedback'],
        ['key' => 'modals', 'partial' => 'modals_confirmations', 'category' => 'feedback'],
        ['key' => 'empty_states', 'partial' => 'empty_states', 'category' => 'feedback'],
        ['key' => 'warning_box', 'partial' => 'comp_warning_box', 'category' => 'feedback'],
        ['key' => 'danger_zone', 'partial' => 'danger_zone', 'category' => 'feedback'],
        ['key' => 'sidebar_concept', 'partial' => 'sidebar_concept', 'category' => 'frontend'],
    ];

    public function __construct(
        TranslatorInterface $translator,
        private readonly ConfigService $configService,
        private readonly CommandService $commandService,
        private readonly ImageService $imageService,
        private readonly ImageLocationService $imageLocationService,
        private readonly ImageRepository $imageRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($translator, 'theme');
    }

    #[Route('/admin/system/theme', name: 'app_admin_system_theme', methods: ['GET', 'POST'])]
    public function theme(Request $request): Response
    {
        $colorsForm = $this->createForm(ThemeColorsType::class);
        $colorsForm->handleRequest($request);

        if ($colorsForm->isSubmitted() && $colorsForm->isValid()) {
            $this->configService->saveColors($colorsForm->getData());
            $this->commandService->rebuildTheme();
            $this->addFlash('success', $this->translator->trans('admin_system_theme.flash_saved'));
        }

        $logoForm = $this->createForm(SiteLogoType::class);
        $logoForm->handleRequest($request);

        if ($logoForm->isSubmitted() && $logoForm->isValid()) {
            $file = $logoForm->get('file')->getData();
            if ($file instanceof UploadedFile) {
                $oldId = $this->configService->getSiteLogoId();
                $image = $this->imageService->upload($file, $this->requireUser(), ImageType::SiteLogo);
                if ($image instanceof Image) {
                    $this->entityManager->flush();
                    $this->imageService->createThumbnails($image, ImageType::SiteLogo);
                    $this->configService->setSiteLogoId($image->getId());
                    if ($oldId !== null) {
                        $this->imageLocationService->removeLocation($oldId, ImageType::SiteLogo, 0);
                    }
                    $this->imageLocationService->addLocation($image->getId(), ImageType::SiteLogo, 0);
                }
            }
        }

        $currentLogo = null;
        $currentLogoId = $this->configService->getSiteLogoId();
        if ($currentLogoId !== null) {
            $currentLogo = $this->imageRepository->find($currentLogoId);
        }

        $adminTop = new AdminTop(info: [new AdminTopInfoText($this->translator->trans('admin_system_theme.intro'))], actions: [
            new AdminTopActionButton(
                label: $this->translator->trans('admin_system_theme.button_gallery'),
                target: $this->generateUrl('app_admin_system_gallery'),
                icon: 'palette',
            ),
        ]);

        return $this->render('admin/system/theme/index.html.twig', [
            'active' => 'system',
            'colorsForm' => $colorsForm,
            'logoForm' => $logoForm,
            'currentLogo' => $currentLogo,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/admin/system/component-gallery', name: 'app_admin_system_gallery', methods: ['GET'])]
    public function gallery(Request $request): Response
    {
        $category = $request->query->getString('category');
        if ($category !== '' && !in_array($category, self::CATEGORIES, true)) {
            $category = '';
        }

        $page = $request->query->getString('page');
        if ($category === '' || !$this->categoryHasEntry($category, $page)) {
            $page = '';
        }

        $gallerySections = $this->buildGallerySections($category, $page);

        $actions = [];
        $subpageDropdown = $this->buildSubpageDropdown($category, $page);
        if ($subpageDropdown !== null) {
            $actions[] = $subpageDropdown;
        }
        $actions[] = $this->buildCategoryDropdown($category);
        $actions[] = new AdminTopActionButton(
            label: $this->translator->trans('global.button_back'),
            target: $this->generateUrl('app_admin_system_theme'),
            icon: 'arrow-left',
        );

        $adminTop = new AdminTop(info: [new AdminTopInfoText($this->translator->trans('admin_system_gallery.page_title'))], actions: $actions);

        return $this->render('admin/system/theme/gallery/index.html.twig', [
            'active' => 'system',
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
            'gallerySections' => $gallerySections,
            'galleryAdminTabsDemo' => $this->buildDemoAdminTabs(),
            'galleryAdminTopList' => $this->buildDemoListAdminTop(),
            'galleryAdminTopDetail' => $this->buildDemoDetailAdminTop(),
        ]);
    }

    /**
     * @return list<array{key: string, partial: string, category: string, section: AdminCollapsibleSection}>
     */
    private function buildGallerySections(string $category, string $page): array
    {
        $filtered = [];
        $seenCategories = [];
        foreach (self::GALLERY_SECTIONS as $section) {
            if ($category !== '' && $section['category'] !== $category) {
                continue;
            }
            if ($page !== '' && $section['key'] !== $page) {
                continue;
            }
            $isOnly = $category !== '' && $page !== '';
            $isFirstInCategory = !isset($seenCategories[$section['category']]);
            $seenCategories[$section['category']] = true;

            $filtered[] = [
                'key' => $section['key'],
                'partial' => $section['partial'],
                'category' => $section['category'],
                'section' => new AdminCollapsibleSection(
                    id: 'g-' . $section['key'],
                    left: [
                        new AdminSectionTextItem(
                            text: $this->translator->trans('admin_system_gallery.section_' . $section['key'] . '_title'),
                            extraClass: 'has-text-weight-semibold',
                        ),
                        new AdminSectionTextItem(
                            text: $this->translator->trans('admin_system_gallery.section_' . $section['key'] . '_caption'),
                            extraClass: 'has-text-grey is-size-7 ml-3',
                        ),
                    ],
                    right: [
                        new AdminSectionTextItem(
                            text: $this->translator->trans('admin_system_gallery.category_' . $section['category']),
                            extraClass: 'tag is-light',
                        ),
                    ],
                    openByDefault: $isOnly || $isFirstInCategory,
                ),
            ];
        }

        return $filtered;
    }

    private function categoryHasEntry(string $category, string $page): bool
    {
        if ($page === '') {
            return false;
        }
        foreach (self::GALLERY_SECTIONS as $section) {
            if ($section['category'] === $category && $section['key'] === $page) {
                return true;
            }
        }

        return false;
    }

    private function buildCategoryDropdown(string $current): AdminTopActionDropdown
    {
        $allLabel = $this->translator->trans('admin_system_gallery.category_all');

        $options = [
            new AdminTopActionDropdownOption(label: $allLabel, target: $this->generateUrl('app_admin_system_gallery'), isActive: $current === ''),
        ];
        foreach (self::CATEGORIES as $cat) {
            $options[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_system_gallery.category_' . $cat),
                target: $this->generateUrl('app_admin_system_gallery', ['category' => $cat]),
                isActive: $current === $cat,
                count: $this->countInCategory($cat),
            );
        }

        $currentLabel = $current === '' ? $allLabel : $this->translator->trans('admin_system_gallery.category_' . $current);

        return new AdminTopActionDropdown(
            label: sprintf('%s %s', $this->translator->trans('admin_system_gallery.category_label'), $currentLabel),
            options: $options,
            icon: 'layer-group',
        );
    }

    private function buildSubpageDropdown(string $category, string $current): ?AdminTopActionDropdown
    {
        if ($category === '') {
            return null;
        }

        $allLabel = $this->translator->trans('admin_system_gallery.subpage_all', [
            '%category%' => $this->translator->trans('admin_system_gallery.category_' . $category),
        ]);

        $options = [
            new AdminTopActionDropdownOption(
                label: $allLabel,
                target: $this->generateUrl('app_admin_system_gallery', ['category' => $category]),
                isActive: $current === '',
            ),
        ];

        foreach (self::GALLERY_SECTIONS as $section) {
            if ($section['category'] !== $category) {
                continue;
            }
            $options[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_system_gallery.section_' . $section['key'] . '_title'),
                target: $this->generateUrl('app_admin_system_gallery', [
                    'category' => $category,
                    'page' => $section['key'],
                ]),
                isActive: $current === $section['key'],
            );
        }

        $currentLabel = $current === '' ? $allLabel : $this->translator->trans('admin_system_gallery.section_' . $current . '_title');

        return new AdminTopActionDropdown(
            label: sprintf('%s %s', $this->translator->trans('admin_system_gallery.subpage_label'), $currentLabel),
            options: $options,
            icon: 'list',
        );
    }

    private function countInCategory(string $category): int
    {
        $count = 0;
        foreach (self::GALLERY_SECTIONS as $section) {
            if ($section['category'] !== $category) {
                continue;
            }
            ++$count;
        }

        return $count;
    }

    private function buildDemoAdminTabs(): AdminTabs
    {
        return new AdminTabs([
            new AdminTab(label: $this->translator->trans('admin_system_gallery.demo_tab_a'), target: '#', icon: 'list', isActive: true),
            new AdminTab(label: $this->translator->trans('admin_system_gallery.demo_tab_b'), target: '#', icon: 'cog'),
        ]);
    }

    private function buildDemoListAdminTop(): AdminTop
    {
        return new AdminTop(info: [
            new AdminTopInfoHtml(sprintf('<strong>1284</strong>&nbsp;%s', htmlspecialchars(
                $this->translator->trans('admin_system_gallery.demo_info_total'),
                ENT_QUOTES,
            ))),
            new AdminTopInfoHtml(sprintf('<strong>42</strong>&nbsp;%s', htmlspecialchars(
                $this->translator->trans('admin_system_gallery.demo_info_in_range'),
                ENT_QUOTES,
            ))),
            new AdminTopInfoHtml(sprintf('<span class="tag is-warning is-medium"><strong>3</strong>&nbsp;%s</span>', htmlspecialchars(
                $this->translator->trans('admin_system_gallery.demo_info_open'),
                ENT_QUOTES,
            ))),
        ], actions: [
            new AdminTopActionDropdown(
                label: $this->translator->trans('admin_system_gallery.demo_filter_label'),
                options: [
                    new AdminTopActionDropdownOption(label: $this->translator->trans('admin_system_gallery.demo_filter_all'), target: '#', isActive: true),
                    new AdminTopActionDropdownOption(label: $this->translator->trans('admin_system_gallery.demo_filter_open'), target: '#', count: 3),
                    new AdminTopActionDropdownOption(label: $this->translator->trans('admin_system_gallery.demo_filter_closed'), target: '#', count: 39),
                ],
                icon: 'filter',
            ),
            new AdminTopActionButton(label: $this->translator->trans('global.button_back'), target: '#', icon: 'arrow-left'),
        ]);
    }

    private function buildDemoDetailAdminTop(): AdminTop
    {
        return new AdminTop(info: [
            new AdminTopInfoHtml(sprintf('<strong>2026-05-02 14:23</strong>&nbsp;<span class="tag is-light">%s</span>', htmlspecialchars(
                $this->translator->trans('admin_system_gallery.demo_info_type'),
                ENT_QUOTES,
            ))),
        ], actions: [
            new AdminTopActionButton(label: $this->translator->trans('global.button_back'), target: '#', icon: 'arrow-left'),
        ]);
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AuthenticationCredentialsNotFoundException();
        }

        return $user;
    }
}
