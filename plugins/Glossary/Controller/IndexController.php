<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Form\GlossaryType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as AbstractSymfonyController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class IndexController extends AbstractSymfonyController
{
    #[Route('/glossary', name: 'app_plugin_glossary', methods: [ 'GET', 'POST'])]
    public function show(Request $request, ManagerRegistry $doctrine): Response
    {
        $glossaryEntityManager = $doctrine->getManager('emGlossary');
        $repo = $glossaryEntityManager->getRepository(Glossary::class);
        $glossary = new Glossary();

        $form = $this->createForm(GlossaryType::class, $glossary);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->getUser() instanceof User) {
                throw new AuthenticationException('Only for logged in users');
            }
            $userId = $this->getUser()->getId();

            $glossary->setCreatedBy($userId);
            $glossary->setCreatedAt(new DateTimeImmutable());
            $glossary->setApproved(false);

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
