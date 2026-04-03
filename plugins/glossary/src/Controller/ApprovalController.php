<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use App\Activity\ActivityService;
use Plugin\Glossary\Activity\Messages\EntryApproved;
use Plugin\Glossary\Service\GlossaryService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/glossary/approval')]
final class ApprovalController extends AbstractGlossaryController
{
    public function __construct(
        GlossaryService $service,
        private readonly ActivityService $activityService,
    ) {
        parent::__construct($service);
    }

    #[Route('/list/{id}', name: 'app_plugin_glossary_approval_list', methods: ['GET'])]
    public function approvalList(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ORGANIZER');

        return $this->renderList('@Glossary/approval.html.twig', [
            'editItem' => $this->service->get($id),
        ]);
    }

    #[Route('/approve/{id}', name: 'app_plugin_glossary_approval_approve', methods: ['GET'])]
    public function approvalApprove(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ORGANIZER');
        $item = $this->service->get($id);
        $this->service->approveNew($id);

        if ($item !== null) {
            $this->activityService->log(EntryApproved::TYPE, $this->getUser(), [
                'glossary_id' => $id,
                'term' => $item->getPhrase(),
            ]);
        }

        return $this->redirectToRoute('app_plugin_glossary');
    }

    #[Route('/deny/{id}', name: 'app_plugin_glossary_approval_deny', methods: ['GET'])]
    public function approvalDeny(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ORGANIZER');
        $this->service->deleteNew($id);

        return $this->redirectToRoute('app_plugin_glossary');
    }
}
