<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Entity\Host;
use App\Form\HostType;
use App\Repository\HostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminHostController extends AbstractController
{
    public function __construct(private readonly HostRepository $repo)
    {
    }

    #[Route('/admin/host/', name: 'app_admin_host')]
    public function hostList(): Response
    {
        return $this->render('admin/host/list.html.twig', [
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

        return $this->render('admin/host/edit.html.twig', [
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

        return $this->render('admin/host/new.html.twig', [
            'active' => 'host',
            'form' => $form,
        ]);
    }
}
