<?php declare(strict_types=1);

namespace Plugin\AdminTables\Controller;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Entity\AdminLink;
use App\Entity\Host;
use App\Repository\HostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\AdminTables\Form\HostType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_FOUNDER')]
class HostController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(section: 'Tables', links: [
            new AdminLink(label: 'menu_admin_host', route: 'app_admin_host', active: 'host', role: 'ROLE_FOUNDER'),
        ]);
    }

    public function __construct(
        private readonly HostRepository $repo,
    ) {}

    #[Route('/admin/host', name: 'app_admin_host')]
    public function hostList(): Response
    {
        return $this->render('@AdminTables/tables/host_list.html.twig', [
            'active' => 'host',
            'hosts' => $this->repo->findAll(),
        ]);
    }

    #[Route('/admin/host/edit/{id}', name: 'app_admin_host_edit', methods: ['GET', 'POST'])]
    public function hostEdit(Request $request, Host $host, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(HostType::class, $host);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_host');
        }

        return $this->render('@AdminTables/tables/host_edit.html.twig', [
            'active' => 'host',
            'host' => $host,
            'form' => $form,
        ]);
    }

    #[Route('/admin/host/add', name: 'app_admin_host_add', methods: ['GET', 'POST'])]
    public function hostAdd(Request $request, EntityManagerInterface $entityManager): Response
    {
        $host = new Host();
        $form = $this->createForm(HostType::class, $host);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($host);
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_host');
        }

        return $this->render('@AdminTables/tables/host_new.html.twig', [
            'active' => 'host',
            'form' => $form,
        ]);
    }
}
