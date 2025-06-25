<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Plugin\Glossary\Entity\Glossary;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as AbstractSymfonyController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class IndexController extends AbstractSymfonyController
{
    #[Route('/glossary', name: 'app_plugin_glossary')]
    public function show(Request $request, ManagerRegistry $doctrine): Response
    {
        $glossaryEntityManager = $doctrine->getManager('emGlossary');
        $glossaryEntityManager->getRepository(Glossary::class)->findAll();

        return $this->render('@Glossary/index.html.twig');
    }
}
