<?php declare(strict_types=1);

namespace App\Controller\Admin\Logs;

use App\Admin\Tabs\AdminTabsInterface;
use App\Repository\NotFoundLogRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/logs/404')]
final class NotFoundLogController extends AbstractLogsController implements AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly NotFoundLogRepository $notFoundLogRepo,
    ) {
        parent::__construct($translator, 'not_found');
    }

    #[Route('', name: 'app_admin_not_found_log')]
    public function list(): Response
    {
        return $this->render('admin/logs/logs_notFound_list.html.twig', [
            'active' => 'logs',
            'activeLog' => '404',
            'list' => $this->notFoundLogRepo->getTop100(),
            'recent' => $this->notFoundLogRepo->getRecent(200),
            'adminTabs' => $this->getTabs(),
        ]);
    }
}
