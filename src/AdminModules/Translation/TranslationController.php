<?php declare(strict_types=1);

namespace App\AdminModules\Translation;

use App\Entity\TranslationSuggestionStatus;
use App\Repository\TranslationSuggestionRepository;
use App\Service\TranslationImportService;
use App\Service\TranslationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class TranslationController extends AbstractController
{
    public function __construct(
        private readonly TranslationService $translationService,
        private readonly TranslationImportService $translationImportService,
        private readonly TranslationSuggestionRepository $translationSuggestionRepo,
    ) {}

    public function translationsSuggestions(): Response
    {
        return $this->render('admin_modules/translation/translation_suggestions.html.twig', [
            'active' => 'suggestions',
            'translationSuggestions' => $this->translationSuggestionRepo->findBy([
                'status' => TranslationSuggestionStatus::Requested,
            ], ['createdAt' => 'DESC']),
        ]);
    }

    public function translationsIndex(): Response
    {
        return $this->render('admin_modules/translation/translation_list.html.twig', [
            'active' => 'edit',
            'translationMatrix' => $this->translationService->getMatrix(),
        ]);
    }

    public function translationsSave(Request $request): Response
    {
        // todo check token
        $this->translationService->saveMatrix($request);
        // flash message

        return $this->redirectToRoute('app_admin_translation_edit');
    }

    public function translationsExtract(): Response
    {
        return $this->render('admin_modules/translation/translation_extract.html.twig', [
            'active' => 'extract',
            'result' => $this->translationImportService->extract(),
        ]);
    }

    public function translationsPublish(): Response
    {
        return $this->render('admin_modules/translation/translation_publish.html.twig', [
            'active' => 'publish',
            'result' => $this->translationService->publish(),
        ]);
    }
}
