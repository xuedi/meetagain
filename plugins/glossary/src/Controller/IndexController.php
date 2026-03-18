<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/glossary')]
final class IndexController extends AbstractGlossaryController
{
    #[Route('', name: 'app_plugin_glossary', methods: ['GET'])]
    public function show(): Response
    {
        return $this->renderList('@Glossary/index.html.twig', []);
    }
}
