<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminLink;
use App\Entity\SupportRequest;
use App\Enum\SupportRequestStatus;
use App\Repository\SupportRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN'), Route('/admin/support')]
class SupportController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'System',
            links: [
                new AdminLink(label: 'Support', route: 'app_admin_support_list', active: 'support'),
            ],
        );
    }

    public function __construct(
        private readonly SupportRequestRepository $supportRequestRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'app_admin_support_list')]
    public function list(): Response
    {
        $requests = $this->supportRequestRepo
            ->createQueryBuilder('sr')
            ->orderBy('sr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/support/list.html.twig', [
            'active' => 'support',
            'requests' => $requests,
        ]);
    }

    #[Route('/mark-read/{id}', name: 'app_admin_support_mark_read', methods: ['POST'])]
    public function markRead(int $id): Response
    {
        $request = $this->supportRequestRepo->find($id);
        if ($request instanceof SupportRequest) {
            $request->setStatus(SupportRequestStatus::Read);
            $this->em->persist($request);
            $this->em->flush();
        }

        return $this->redirectToRoute('app_admin_support_list');
    }
}
