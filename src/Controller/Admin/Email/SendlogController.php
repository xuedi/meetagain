<?php declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Entity\EmailQueue;
use App\Enum\EmailQueueStatus;
use App\Repository\EmailQueueRepository;
use App\Service\Email\Delivery\EmailDeliveryProviderInterface;
use App\Service\Email\Delivery\EmailDeliveryStatusSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
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

    #[Route('/{id}/clear-cap', name: 'app_admin_email_sendlog_clear_cap', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function clearCap(EmailQueue $email, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('clear_cap_' . $email->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $email->setMaxSendBy(null);
        $email->setStatus(EmailQueueStatus::Pending);
        $email->setErrorMessage(null);
        $this->em->flush();

        $this->addFlash('success', $this->translator->trans('admin_email.flash_cap_cleared'));

        return $this->redirectToRoute('app_admin_email_sendlog_show', ['id' => $email->getId()]);
    }

    #[Route('/sync', name: 'app_admin_email_sendlog_sync', methods: ['POST'])]
    public function sync(): Response
    {
        $result = $this->syncService->syncPending(200);

        if ($result->available) {
            $this->addFlash('success', $this->translator->trans('admin_email.flash_sync_success', [
                '%updated%' => $result->updated,
                '%checked%' => $result->checked,
            ]));
        }
        if (!$result->available) {
            $this->addFlash('warning', $this->translator->trans('admin_email.flash_provider_not_configured'));
        }

        return $this->redirectToRoute('app_admin_email_sendlog');
    }
}
