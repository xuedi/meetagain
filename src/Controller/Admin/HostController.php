<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminLink;
use App\Entity\Host;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Filter\Admin\Host\AdminHostListFilterService;
use App\Form\HostType;
use App\Repository\EventRepository;
use App\Repository\HostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ORGANIZER'), Route('/admin/hosts')]
final class HostController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'admin_shell.section_content',
            links: [
                new AdminLink(
                    label: 'admin_shell.menu_host',
                    route: 'app_admin_host',
                    active: 'host',
                    role: 'ROLE_ORGANIZER',
                ),
            ],
            sectionPriority: 50,
        );
    }

    public function __construct(
        private readonly HostRepository $repo,
        private readonly EventRepository $eventRepo,
        private readonly EntityActionDispatcher $entityActionDispatcher,
        private readonly AdminHostListFilterService $hostFilterService,
    ) {}

    #[Route('', name: 'app_admin_host')]
    public function list(): Response
    {
        $filterResult = $this->hostFilterService->getHostIdFilter();
        $hostIds = $filterResult->getHostIds();

        return $this->render('admin/host/list.html.twig', [
            'active' => 'host',
            'hosts' => $this->repo->findAllForAdmin($hostIds),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_host_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Host $host, EntityManagerInterface $entityManager): Response
    {
        if (!$this->hostFilterService->isHostAccessible($host->getId())) {
            throw $this->createNotFoundException('Host not found in current context.');
        }

        $form = $this->createForm(HostType::class, $host);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Host updated successfully');

            return $this->redirectToRoute('app_admin_host');
        }

        $eventsUsingHost = $this->eventRepo->findByHost($host);

        return $this->render('admin/host/edit.html.twig', [
            'active' => 'host',
            'host' => $host,
            'form' => $form,
            'events_using_host' => $eventsUsingHost,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_host_delete', methods: ['GET'])]
    public function delete(Host $host, EntityManagerInterface $entityManager): Response
    {
        if (!$this->hostFilterService->isHostAccessible($host->getId())) {
            throw $this->createNotFoundException('Host not found in current context.');
        }

        $eventsUsingHost = $this->eventRepo->findByHost($host);
        if (count($eventsUsingHost) > 0) {
            $this->addFlash(
                'error',
                'Cannot delete host. It is currently used in ' . count($eventsUsingHost) . ' event(s).',
            );

            return $this->redirectToRoute('app_admin_host_edit', ['id' => $host->getId()]);
        }

        $hostId = $host->getId();
        $entityManager->remove($host);
        $entityManager->flush();

        $this->entityActionDispatcher->dispatch(EntityAction::DeleteHost, $hostId);

        $this->addFlash('success', 'Host deleted successfully');

        return $this->redirectToRoute('app_admin_host');
    }

    #[Route('/add', name: 'app_admin_host_add', methods: ['GET', 'POST'])]
    public function add(Request $request, EntityManagerInterface $entityManager): Response
    {
        $host = new Host();
        $form = $this->createForm(HostType::class, $host);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($host);
            $entityManager->flush();

            $this->entityActionDispatcher->dispatch(EntityAction::CreateHost, $host->getId());

            $this->addFlash('success', 'Host created successfully');

            return $this->redirectToRoute('app_admin_host');
        }

        return $this->render('admin/host/edit.html.twig', [
            'active' => 'host',
            'host' => $host,
            'form' => $form,
        ]);
    }
}
