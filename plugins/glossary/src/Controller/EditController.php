<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use App\Entity\User;
use Plugin\Glossary\Entity\Category;
use Plugin\Glossary\Entity\SuggestionField;
use Plugin\Glossary\Form\GlossaryType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

#[Route('/glossary/edit')]
class EditController extends AbstractGlossaryController
{
    #[Route('/{id}', name: 'app_plugin_glossary_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ?int $id = null): Response
    {
        $newGlossary = $this->service->get($id);

        $form = $this->createForm(GlossaryType::class, $newGlossary);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->service->detach($newGlossary); // dont auto save entity
            if (!$this->getUser() instanceof User) {
                throw new AuthenticationException('Only for logged in users');
            }
            $this->service->generateSuggestions(
                newGlossary: $newGlossary,
                id: $id,
                userId: $this->getUser()->getId(),
                isManager: $this->isGranted('ROLE_MANAGER')
            );

            return $this->redirectToRoute('app_plugin_glossary');
        }

        return $this->renderList('@Glossary/edit.html.twig', [
            'categoryFieldValue' => SuggestionField::Category->value,
            'categoryNames' => Category::getNames(),
            'editItem' => $this->service->get($id),
            'form' => $form,
        ]);
    }
}
