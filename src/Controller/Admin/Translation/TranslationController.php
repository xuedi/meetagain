<?php declare(strict_types=1);

namespace App\Controller\Admin\Translation;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Entity\TranslationSuggestionStatus;
use App\Repository\TranslationSuggestionRepository;
use App\Service\TranslationImportService;
use App\Service\TranslationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN'), Route('/admin/translation')]
class TranslationController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    public function __construct(
        private readonly TranslationService $translationService,
        private readonly TranslationImportService $translationImportService,
        private readonly TranslationSuggestionRepository $translationSuggestionRepo,
    ) {}

    #[Route('', name: 'app_admin_translation')]
    public function translationsIndex(): Response
    {
        return $this->render('admin/translation/translation_list.html.twig', [
            'active' => 'edit',
            'translationMatrix' => $this->translationService->getMatrix(),
        ]);
    }

    #[Route('/save', name: 'app_admin_translation_save', methods: ['POST'])]
    public function translationsSave(Request $request): Response
    {
        // todo check token
        $this->translationService->saveMatrix($request);
        // flash message

        return $this->redirectToRoute('app_admin_translation');
    }

    #[Route('/actions', name: 'app_admin_translation_actions')]
    public function translationsActions(): Response
    {
        return $this->render('admin/translation/translation_actions.html.twig', [
            'active' => 'actions',
        ]);
    }

    #[Route('/extract', name: 'app_admin_translation_extract', methods: ['POST'])]
    public function translationsExtract(): Response
    {
        $result = $this->translationImportService->extract();
        $this->addFlash('success', sprintf(
            'Extracted %d translations (%d new, %d orphans removed)',
            $result->count,
            $result->new,
            $result->deleted,
        ));

        return $this->redirectToRoute('app_admin_translation_actions');
    }

    #[Route('/publish', name: 'app_admin_translation_publish', methods: ['POST'])]
    public function translationsPublish(): Response
    {
        $result = $this->translationService->publish();
        $this->addFlash('success', sprintf(
            'Published %d translations to %s',
            $result->published,
            implode(', ', $result->languages),
        ));

        return $this->redirectToRoute('app_admin_translation_actions');
    }

    #[Route('/suggestions', name: 'app_admin_translation_suggestion')]
    public function translationsSuggestions(): Response
    {
        return $this->render('admin/translation/translation_suggestions.html.twig', [
            'active' => 'suggestions',
            'translationSuggestions' => $this->translationSuggestionRepo->findBy([
                'status' => TranslationSuggestionStatus::Requested,
            ], ['createdAt' => 'DESC']),
        ]);
    }
}
