<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/glossary/delete')]
class DeleteController extends AbstractGlossaryController
{
    #[Route('/view/{id}', name: 'app_plugin_glossary_delete_view', methods: ['GET'])]
    public function deleteView(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

        return $this->renderList('@Glossary/delete.html.twig', [
            'editItem' => $this->service->get($id),
        ]);
    }

    #[Route('/{id}', name: 'app_plugin_glossary_delete', methods: ['GET'])]
    public function delete(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        $this->service->delete($id);

        return $this->redirectToRoute('app_plugin_glossary');
    }
}
