<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\Form\SettingsType;
use App\Form\WebsiteImageType;
use App\Repository\ImageRepository;
use App\Security\Permission\Attribute\PermissionAttribute;
use App\Service\Cms\CmsPageCacheService;
use App\Service\Config\ConfigService;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system')]
final class ConfigController extends AbstractSettingsController implements AdminNavigationInterface, AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly ConfigService $configService,
        private readonly ImageService $imageService,
        private readonly ImageLocationService $imageLocationService,
        private readonly ImageRepository $imageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CmsPageCacheService $cmsPageCacheService,
    ) {
        parent::__construct($translator, 'config');
    }

    #[Route('', name: 'app_admin_system')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_admin_system_config');
    }

    #[Route('/config', name: 'app_admin_system_config', methods: ['GET', 'POST'])]
    public function config(Request $request): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SETTINGS_READ);

        $form = $this->createForm(SettingsType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SETTINGS_UPDATE);
            $this->configService->saveForm($form->getData());
            $this->addFlash('success', $this->translator->trans('admin_system_config.flash_saved'));
        }

        $websiteImageForm = $this->createForm(WebsiteImageType::class);
        $websiteImageForm->handleRequest($request);

        if ($websiteImageForm->isSubmitted() && $websiteImageForm->isValid()) {
            $file = $websiteImageForm->get('file')->getData();
            if ($file instanceof UploadedFile) {
                $oldId = $this->configService->getWebsiteImageId();
                $image = $this->imageService->upload($file, $this->requireUser(), ImageType::WebsiteImage);
                if ($image instanceof Image) {
                    $this->entityManager->flush();
                    $this->imageService->createThumbnails($image, ImageType::WebsiteImage);
                    $this->configService->setWebsiteImageId($image->getId());
                    if ($oldId !== null) {
                        $this->imageLocationService->removeLocation($oldId, ImageType::WebsiteImage, 0);
                    }
                    $this->imageLocationService->addLocation($image->getId(), ImageType::WebsiteImage, 0);
                    $this->cmsPageCacheService->invalidateAll();
                    $this->addFlash('success', $this->translator->trans('admin_system_config.flash_website_image_saved'));
                }
            }
        }

        $currentWebsiteImage = null;
        $currentWebsiteImageId = $this->configService->getWebsiteImageId();
        if ($currentWebsiteImageId !== null) {
            $currentWebsiteImage = $this->imageRepository->find($currentWebsiteImageId);
        }

        $adminTop = new AdminTop(
            info: [new AdminTopInfoText($this->translator->trans('admin_system_config.intro'))],
        );

        return $this->render('admin/system/config/index.html.twig', [
            'active' => 'system',
            'form' => $form,
            'websiteImageForm' => $websiteImageForm,
            'currentWebsiteImage' => $currentWebsiteImage,
            'config' => $this->configService->getBooleanConfigs(),
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/boolean/{name}', name: 'app_admin_system_boolean', methods: ['POST'])]
    public function boolean(Request $request, string $name): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SETTINGS_UPDATE);

        $value = $this->configService->toggleBoolean($name);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['newStatus' => $value]);
        }

        return $this->redirectToRoute('app_admin_system_config');
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
