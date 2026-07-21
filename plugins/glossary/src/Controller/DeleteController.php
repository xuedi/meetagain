<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use App\Activity\ActivityService;
use Plugin\Glossary\Activity\Messages\EntryDeleted;
use Plugin\Glossary\Service\GlossaryService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
        return $this->renderPage('@Glossary/delete.html.twig', [
            'editItem' => $this->service->get($id),
        ]);
    }

    #[Route('/{id}', name: 'app_plugin_glossary_delete', methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('admin_glossary_delete' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

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
