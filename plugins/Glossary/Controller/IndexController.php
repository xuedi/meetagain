<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use DateTimeImmutable;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Form\GlossaryType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as AbstractSymfonyController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class IndexController extends AbstractSymfonyController
{
    #[Route('/glossary/{glossary}', name: 'app_plugin_glossary', methods: [ 'GET', 'POST'])]
    public function show(Request $request, ManagerRegistry $doctrine, ?Glossary $glossary = null): Response
    {
        $glossaryEntityManager = $doctrine->getManager('emGlossary');
        $repo = $glossaryEntityManager->getRepository(Glossary::class);

        $form = $this->createForm(GlossaryType::class, $glossary);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $glossary->setSuggestedBy($this->getUser()->getId());
            $glossary->setCreatedBy($this->getUser()->getId());
            $glossary->setCreatedAt(new DateTimeImmutable());

            $glossaryEntityManager->persist($glossary);
            $glossaryEntityManager->flush();

            return $this->redirectToRoute('app_plugin_glossary');
        }

        return $this->render('@Glossary/index.html.twig', [
            'glossary' => $repo->findAll(),
            'form' => $form,
        ]);
    }
}
