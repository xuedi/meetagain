<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
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

        $adminTop = new AdminTop(
            info: [new AdminTopInfoText($this->translator->trans('admin_system_theme.intro'))],
            actions: [
                new AdminTopActionButton(
                    label: $this->translator->trans('admin_system_theme.button_gallery'),
                    target: $this->generateUrl('app_admin_system_gallery'),
                    icon: 'palette',
                ),
            ],
        );

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
    public function gallery(): Response
    {
        $adminTop = new AdminTop(
            actions: [
                new AdminTopActionButton(
                    label: $this->translator->trans('global.button_back'),
                    target: $this->generateUrl('app_admin_system_theme'),
                    icon: 'arrow-left',
                ),
            ],
        );

        return $this->render('admin/system/theme/gallery/index.html.twig', [
            'active' => 'system',
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
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
