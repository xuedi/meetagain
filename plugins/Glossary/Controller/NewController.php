<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use App\Entity\User;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Form\GlossaryType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

#[Route('/glossary')]
class NewController extends AbstractGlossaryController
{
    #[Route('/new', name: 'app_plugin_glossary_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $glossary = new Glossary();

        $form = $this->createForm(GlossaryType::class, $glossary);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->getUser() instanceof User) {
                throw new AuthenticationException('Only for logged in users');
            }
            $this->service->createNew($glossary, $this->getUser()->getId());

            return $this->redirectToRoute('app_plugin_glossary');
        }

        return $this->renderList('@Glossary/new.html.twig', [
            'form' => $form,
        ]);
    }
}
