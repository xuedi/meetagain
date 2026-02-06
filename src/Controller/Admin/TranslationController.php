<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TranslationSuggestionStatus;
use App\Repository\TranslationSuggestionRepository;
use App\Service\TranslationImportService;
use App\Service\TranslationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TranslationController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'Translation',
            label: 'menu_admin_translation',
            route: 'app_admin_translation',
            active: 'translation',
        );
    }

    public function __construct(
        private readonly TranslationService $translationService,
        private readonly TranslationImportService $translationImportService,
        private readonly TranslationSuggestionRepository $translationSuggestionRepo,
    ) {}

    #[Route('/admin/translations/suggestions', name: 'app_admin_translation_suggestion')]
    public function translationsSuggestions(): Response
    {
        return $this->render('admin/translation/translation_suggestions.html.twig', [
            'active' => 'suggestions',
            'translationSuggestions' => $this->translationSuggestionRepo->findBy([
                'status' => TranslationSuggestionStatus::Requested,
            ], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/admin/translation', name: 'app_admin_translation')]
    public function translationsIndex(): Response
    {
        return $this->render('admin/translation/translation_list.html.twig', [
            'active' => 'edit',
            'translationMatrix' => $this->translationService->getMatrix(),
        ]);
    }

    #[Route('/admin/translation/save', name: 'app_admin_translation_save', methods: ['POST'])]
    public function translationsSave(Request $request): Response
    {
        // todo check token
        $this->translationService->saveMatrix($request);
        // flash message

        return $this->redirectToRoute('app_admin_translation');
    }

    #[Route('/admin/translation/extract', name: 'app_admin_translation_extract')]
    public function translationsExtract(): Response
    {
        return $this->render('admin/translation/translation_extract.html.twig', [
            'active' => 'extract',
            'result' => $this->translationImportService->extract(),
        ]);
    }

    #[Route('/admin/translation/publish', name: 'app_admin_translation_publish')]
    public function translationsPublish(): Response
    {
        return $this->render('admin/translation/translation_publish.html.twig', [
            'active' => 'publish',
            'result' => $this->translationService->publish(),
        ]);
    }
}
