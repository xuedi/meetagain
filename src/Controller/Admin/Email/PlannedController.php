<?php declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Service\Email\PlannedEmailService;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN'), Route('/admin/email/planned')]
final class PlannedController extends AbstractAdminController
{
    public function __construct(
        private readonly PlannedEmailService $plannedEmailService,
    ) {}

    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    #[Route('', name: 'app_admin_email_planned')]
    public function list(): Response
    {
        $from = new DateTimeImmutable();
        $to = $from->modify('+14 days');
        $items = $this->plannedEmailService->getPlannedItems($from, $to);

        return $this->render('admin/email/planned/list.html.twig', [
            'active' => 'email',
            'activeSection' => 'planned',
            'items' => $items,
        ]);
    }
}
