<?php declare(strict_types=1);

namespace App\AdminModules\Tables;

use App\Entity\Host;
use App\Form\HostType;
use App\Repository\HostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class HostController extends AbstractController
{
    public function __construct(
        private readonly HostRepository $repo,
    ) {}

    public function hostList(): Response
    {
        return $this->render('admin_modules/tables/host_list.html.twig', [
            'active' => 'host',
            'hosts' => $this->repo->findAll(),
        ]);
    }

    public function hostEdit(Request $request, Host $host, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(HostType::class, $host);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_host');
        }

        return $this->render('admin_modules/tables/host_edit.html.twig', [
            'active' => 'host',
            'host' => $host,
            'form' => $form,
        ]);
    }

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

        return $this->render('admin_modules/tables/host_new.html.twig', [
            'active' => 'host',
            'form' => $form,
        ]);
    }
}
