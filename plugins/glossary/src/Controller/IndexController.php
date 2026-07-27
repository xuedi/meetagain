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
        return $this->renderPage('@Glossary/index.html.twig');
    }

    #[Route('/{id}', name: 'app_plugin_glossary_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id): Response
    {
        $entry = $this->service->get($id);
        if ($entry === null) {
            throw $this->createNotFoundException();
        }

        return $this->renderPage('@Glossary/detail.html.twig', [
            'entry' => $entry,
        ]);
    }
}
