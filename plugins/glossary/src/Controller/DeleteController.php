<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use App\Activity\ActivityService;
use Plugin\Glossary\Activity\Messages\EntryDeleted;
use Plugin\Glossary\Service\GlossaryService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/glossary/delete')]
#[IsGranted('ROLE_ORGANIZER')]
final class DeleteController extends AbstractGlossaryController
{
    public function __construct(
        GlossaryService $service,
        private readonly ActivityService $activityService,
    ) {
        parent::__construct($service);
    }

    #[Route('/view/{id}', name: 'app_plugin_glossary_delete_view', methods: ['GET'])]
    public function deleteView(int $id): Response
    {
        return $this->renderList('@Glossary/delete.html.twig', [
            'editItem' => $this->service->get($id),
        ]);
    }

    #[Route('/{id}', name: 'app_plugin_glossary_delete', methods: ['GET'])]
    public function delete(int $id): Response
    {
        $item = $this->service->get($id);
        $this->service->delete($id);

        if ($item !== null) {
            $this->activityService->log(EntryDeleted::TYPE, $this->getUser(), [
                'glossary_id' => $id,
                'term' => $item->getPhrase(),
            ]);
        }

        return $this->redirectToRoute('app_plugin_glossary');
    }
}
