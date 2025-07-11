<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use Plugin\Glossary\Entity\Category;
use Plugin\Glossary\Entity\SuggestionField;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/glossary/suggestion')]
class SuggestionController extends AbstractGlossaryController
{
    #[Route('/list/{id}', name: 'app_plugin_glossary_suggestion_list', methods: ['GET'])]
    public function suggestionList(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

        return $this->renderList('@Glossary/suggestion.html.twig', [
            'categoryFieldValue' => SuggestionField::Category->value,
            'categoryNames' => Category::getNames(),
            'editItem' => $this->service->get($id),
        ]);
    }

    #[Route('/apply/{id}/{hash}', name: 'app_plugin_glossary_suggestion_apply', methods: ['GET'])]
    public function suggestionApply(int $id, string $hash): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        $leftOver = $this->service->applySuggestion($id, $hash);

        if ($leftOver === 0) {
            return $this->redirectToRoute('app_plugin_glossary');
        } else {
            return $this->redirectToRoute('app_plugin_glossary_suggestion_list', ['id' => $id]);
        }
    }

    #[Route('/delete/{id}/{hash}', name: 'app_plugin_glossary_suggestion_delete', methods: ['GET'])]
    public function suggestionDelete(int $id, string $hash): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        $leftOver = $this->service->denySuggestion($id, $hash);

        if ($leftOver === 0) {
            return $this->redirectToRoute('app_plugin_glossary');
        } else {
            return $this->redirectToRoute('app_plugin_glossary_suggestion_list', ['id' => $id]);
        }
    }
}
