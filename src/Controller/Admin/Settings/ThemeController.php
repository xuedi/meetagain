<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Entity\Image;
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
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system/theme')]
final class ThemeController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    public function __construct(
        private readonly ConfigService $configService,
        private readonly CommandService $commandService,
        private readonly TranslatorInterface $translator,
        private readonly ImageService $imageService,
        private readonly ImageLocationService $imageLocationService,
        private readonly ImageRepository $imageRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('', name: 'app_admin_system_theme', methods: ['GET', 'POST'])]
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
                $image = $this->imageService->upload($file, $this->getAuthedUser(), ImageType::SiteLogo);
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

        return $this->render('admin/system/theme/index.html.twig', [
            'active' => 'system',
            'colorsForm' => $colorsForm,
            'logoForm' => $logoForm,
            'currentLogo' => $currentLogo,
        ]);
    }
}
