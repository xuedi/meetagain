<?php declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Entity\EmailQueue;
use App\Repository\EmailQueueRepository;
use App\Service\Email\Provider\EmailDeliveryProviderInterface;
use App\Service\Email\Provider\EmailDeliveryStatusSyncService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN'), Route('/admin/email/sendlog')]
final class SendlogController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    public function __construct(
        private readonly EmailQueueRepository $emailQueueRepo,
        private readonly EmailDeliveryProviderInterface $provider,
        private readonly EmailDeliveryStatusSyncService $syncService,
    ) {}

    #[Route('', name: 'app_admin_email_sendlog')]
    public function sendlog(): Response
    {
        $emails = $this->emailQueueRepo
            ->createQueryBuilder('eq')
            ->orderBy('eq.createdAt', 'DESC')
            ->setMaxResults(1000)
            ->getQuery()
            ->getResult();

        return $this->render('admin/email/sendlog/list.html.twig', [
            'active' => 'email',
            'emails' => $emails,
            'providerAvailable' => $this->provider->isAvailable(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_email_sendlog_show', requirements: ['id' => '\d+'])]
    public function show(EmailQueue $email): Response
    {
        return $this->render('admin/email/sendlog/show.html.twig', [
            'active' => 'email',
            'email' => $email,
        ]);
    }

    #[Route('/sync', name: 'app_admin_email_sendlog_sync', methods: ['POST'])]
    public function sync(): Response
    {
        $result = $this->syncService->syncPending(200);

        if ($result->available) {
            $this->addFlash('success', sprintf(
                'Synced %d of %d email statuses from provider.',
                $result->updated,
                $result->checked,
            ));
        } else {
            $this->addFlash('warning', 'Email delivery provider is not configured.');
        }

        return $this->redirectToRoute('app_admin_email_sendlog');
    }
}
