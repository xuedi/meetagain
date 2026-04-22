<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Entity\Image;
use App\Enum\ImageType;
use App\Entity\Language;
use App\Form\LanguageType;
use App\Repository\LanguageRepository;
use App\Service\Config\LanguageService;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/language')]
final class LanguageController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    public function __construct(
        private readonly LanguageRepository $repo,
        private readonly EntityManagerInterface $em,
        private readonly LanguageService $languageService,
        private readonly ImageService $imageService,
        private readonly ImageLocationService $imageLocationService,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('', name: 'app_admin_language')]
    public function list(): Response
    {
        return $this->render('admin/system/language/list.html.twig', [
            'active' => 'system',
            'languages' => $this->repo->findAllOrdered(),
        ]);
    }

    #[Route('/add', name: 'app_admin_language_add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        $language = new Language();
        $form = $this->createForm(LanguageType::class, $language);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImageUpload($form, $language);

            $this->em->persist($language);
            $this->em->flush();

            $newTile = $language->getTileImage();
            if ($newTile !== null) {
                $this->imageLocationService->addLocation($newTile->getId(), ImageType::LanguageTile, $language->getId());
            }

            $this->languageService->invalidateCache();

            $this->addFlash('success', $this->translator->trans('admin_system.flash_language_added'));

            return $this->redirectToRoute('app_admin_language');
        }

        return $this->render('admin/system/language/edit.html.twig', [
            'active' => 'system',
            'form' => $form,
            'language' => $language,
            'isEdit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_language_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Language $language): Response
    {
        $form = $this->createForm(LanguageType::class, $language, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $oldTileId = $language->getTileImage()?->getId();
            $this->handleImageUpload($form, $language);

            $this->em->flush();

            $newTile = $language->getTileImage();
            if ($newTile !== null && $newTile->getId() !== $oldTileId) {
                if ($oldTileId !== null) {
                    $this->imageLocationService->removeLocation($oldTileId, ImageType::LanguageTile, $language->getId());
                }
                $this->imageLocationService->addLocation($newTile->getId(), ImageType::LanguageTile, $language->getId());
            }

            $this->languageService->invalidateCache();

            $this->addFlash('success', $this->translator->trans('admin_system.flash_language_updated'));

            return $this->redirectToRoute('app_admin_language');
        }

        return $this->render('admin/system/language/edit.html.twig', [
            'active' => 'system',
            'form' => $form,
            'language' => $language,
            'isEdit' => true,
        ]);
    }

    #[Route('/{id}/toggle', name: 'app_admin_language_toggle', methods: ['GET'])]
    public function toggle(Language $language): Response
    {
        $language->setEnabled(!$language->isEnabled());
        $this->em->flush();
        $this->languageService->invalidateCache();

        return $this->redirectToRoute('app_admin_language');
    }

    private function handleImageUpload(mixed $form, Language $language): void
    {
        $imageData = $form->get('tileImage')->getData();
        if (!$imageData instanceof UploadedFile) {
            return;
        }

        $user = $this->getUser();

        $image = $this->imageService->upload($imageData, $user, ImageType::LanguageTile);
        if ($image instanceof Image) {
            $language->setTileImage($image);
            $this->imageService->createThumbnails($image, ImageType::LanguageTile);
        }
    }
}
