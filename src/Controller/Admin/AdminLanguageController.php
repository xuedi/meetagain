<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Language;
use App\Form\LanguageType;
use App\Repository\LanguageRepository;
use App\Service\LanguageService;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminLanguageController extends AbstractController
{
    public function __construct(
        private readonly LanguageRepository $repo,
        private readonly EntityManagerInterface $em,
        private readonly LanguageService $languageService,
        private readonly TranslationService $translationService,
    ) {
    }

    #[Route('/admin/language/', name: 'app_admin_language')]
    public function list(): Response
    {
        return $this->render('admin/language/list.html.twig', [
            'active' => 'language',
            'languages' => $this->repo->findAllOrdered(),
        ]);
    }

    #[Route('/admin/language/add', name: 'app_admin_language_add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        $language = new Language();
        $form = $this->createForm(LanguageType::class, $language);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($language);
            $this->em->flush();
            $this->languageService->invalidateCache();
            $this->translationService->publish();

            $this->addFlash('success', 'Language added successfully');
            return $this->redirectToRoute('app_admin_language');
        }

        return $this->render('admin/language/edit.html.twig', [
            'active' => 'language',
            'form' => $form,
            'language' => $language,
            'isEdit' => false,
        ]);
    }

    #[Route('/admin/language/{id}/edit', name: 'app_admin_language_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Language $language): Response
    {
        $form = $this->createForm(LanguageType::class, $language, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->languageService->invalidateCache();
            $this->translationService->publish();

            $this->addFlash('success', 'Language updated successfully');
            return $this->redirectToRoute('app_admin_language');
        }

        return $this->render('admin/language/edit.html.twig', [
            'active' => 'language',
            'form' => $form,
            'language' => $language,
            'isEdit' => true,
        ]);
    }

    #[Route('/admin/language/{id}/toggle', name: 'app_admin_language_toggle')]
    public function toggle(Language $language): Response
    {
        $language->setEnabled(!$language->isEnabled());
        $this->em->flush();
        $this->languageService->invalidateCache();
        $this->translationService->publish();

        $status = $language->isEnabled() ? 'enabled' : 'disabled';
        $this->addFlash('success', sprintf('Language %s successfully', $status));

        return $this->redirectToRoute('app_admin_language');
    }
}
