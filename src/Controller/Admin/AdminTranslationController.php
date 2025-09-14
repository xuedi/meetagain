<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\TranslationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminTranslationController extends AbstractController
{
    #[Route('/admin/translations/edit', name: 'app_admin_translation_edit')]
    public function translationsIndex(TranslationService $translationService): Response
    {
        return $this->render('admin/translations/list.html.twig', [
            'active' => 'edit',
            'translationMatrix' => $translationService->getMatrix(),
        ]);
    }

    #[Route('/admin/translations/save', name: 'app_admin_translation_save')]
    public function translationsSave(TranslationService $translationService, Request $request): Response
    {
        // todo check token
        $translationService->saveMatrix($request);
        // flash message

        return $this->redirectToRoute('app_admin_translation_edit');
    }

    #[Route('/admin/translations/extract', name: 'app_admin_translation_extract')]
    public function translationsExtract(TranslationService $translationService): Response
    {
        return $this->render('admin/translations/extract.html.twig', [
            'active' => 'extract',
            'result' => $translationService->extract(),
        ]);
    }

    #[Route('/admin/translations/publish', name: 'app_admin_translation_publish')]
    public function translationsPublish(TranslationService $translationService): Response
    {
        return $this->render('admin/translations/publish.html.twig', [
            'active' => 'publish',
            'result' => $translationService->publish(),
        ]);
    }
}
