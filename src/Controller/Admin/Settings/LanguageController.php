<?php

declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Entity\Image;
use App\Entity\Language;
use App\Entity\User;
use App\Enum\ImageType;
use App\Form\LanguageType;
use App\Repository\LanguageRepository;
use App\Service\Config\LanguageService;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/language')]
final class LanguageController extends AbstractSettingsController implements
    AdminNavigationInterface,
    AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly LanguageRepository $repo,
        private readonly EntityManagerInterface $em,
        private readonly LanguageService $languageService,
        private readonly ImageService $imageService,
        private readonly ImageLocationService $imageLocationService,
    ) {
        parent::__construct($translator, 'language');
    }

    #[Route('', name: 'app_admin_language')]
    public function list(): Response
    {
        $languages = $this->repo->findAllOrdered();
        $enabledCount = 0;
        foreach ($languages as $language) {
            if (!$language->isEnabled()) {
                continue;
            }

            ++$enabledCount;
        }

        $adminTop = new AdminTop(info: [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                count($languages),
                $this->translator->trans('admin_system_language.summary_total'),
            )),
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $enabledCount,
                $this->translator->trans('admin_system_language.summary_enabled'),
            )),
        ]);

        return $this->render('admin/system/language/list.html.twig', [
            'active' => 'system',
            'languages' => $languages,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
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
                $this->imageLocationService->addLocation(
                    $newTile->getId(),
                    ImageType::LanguageTile,
                    $language->getId(),
                );
            }

            $this->languageService->invalidateCache();

            $this->addFlash('success', $this->translator->trans('admin_system_language.flash_added'));

            return $this->redirectToRoute('app_admin_language');
        }

        return $this->render('admin/system/language/edit.html.twig', [
            'active' => 'system',
            'form' => $form,
            'language' => $language,
            'isEdit' => false,
            'adminTop' => $this->buildEditAdminTop(false),
            'adminTabs' => $this->getTabs(),
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
                    $this->imageLocationService->removeLocation(
                        $oldTileId,
                        ImageType::LanguageTile,
                        $language->getId(),
                    );
                }
                $this->imageLocationService->addLocation(
                    $newTile->getId(),
                    ImageType::LanguageTile,
                    $language->getId(),
                );
            }

            $this->languageService->invalidateCache();

            $this->addFlash('success', $this->translator->trans('admin_system_language.flash_updated'));

            return $this->redirectToRoute('app_admin_language_edit', ['id' => $language->getId()]);
        }

        return $this->render('admin/system/language/edit.html.twig', [
            'active' => 'system',
            'form' => $form,
            'language' => $language,
            'isEdit' => true,
            'adminTop' => $this->buildEditAdminTop(true, $language),
            'adminTabs' => $this->getTabs(),
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

    private function buildEditAdminTop(bool $isEdit, ?Language $language = null): AdminTop
    {
        $titleKey = $isEdit ? 'admin_system_language.page_title_edit' : 'admin_system_language.page_title_add';
        $info = [
            new AdminTopInfoHtml(sprintf('<strong>%s</strong>', htmlspecialchars(
                $this->translator->trans($titleKey),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            ))),
        ];
        if ($isEdit && $language !== null) {
            $info[] = new AdminTopInfoHtml(sprintf('<span class="tag is-light">%s</span>', htmlspecialchars(
                (string) $language->getCode(),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            )));
        }

        return new AdminTop(info: $info, actions: [
            new AdminTopActionButton(
                label: $this->translator->trans('global.button_back'),
                target: $this->generateUrl('app_admin_language'),
                icon: 'arrow-left',
            ),
        ]);
    }

    private function handleImageUpload(FormInterface $form, Language $language): void
    {
        $imageData = $form->get('tileImage')->getData();
        if (!$imageData instanceof UploadedFile) {
            return;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AuthenticationCredentialsNotFoundException();
        }

        $image = $this->imageService->upload($imageData, $user, ImageType::LanguageTile);
        if ($image instanceof Image) {
            $language->setTileImage($image);
            $this->imageService->createThumbnails($image, ImageType::LanguageTile);
        }
    }
}
