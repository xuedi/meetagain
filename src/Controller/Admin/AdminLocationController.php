<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Entity\Location;
use App\Form\LocationType;
use App\Repository\LocationRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminLocationController extends AbstractController
{
    public function __construct(private readonly LocationRepository $repo)
    {
    }

    #[Route('/admin/location/', name: 'app_admin_location')]
    public function locationList(): Response
    {
        return $this->render('admin/location/list.html.twig', [
            'active' => 'location',
            'locations' => $this->repo->findAll(),
        ]);
    }

    #[Route('/admin/location/edit/{id}', name: 'app_admin_location_edit', methods: ['GET', 'POST'])]
    public function locationEdit(Request $request, Location $location, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(LocationType::class, $location);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_location');
        }

        return $this->render('admin/location/edit.html.twig', [
            'active' => 'location',
            'location' => $location,
            'form' => $form,
        ]);
    }

    #[Route('/admin/location/add', name: 'app_admin_location_add', methods: ['GET', 'POST'])]
    public function locationAdd(Request $request, EntityManagerInterface $entityManager): Response
    {
        $location = new Location();
        $form = $this->createForm(LocationType::class, $location);
        $form->remove('createdAt');
        $form->remove('user');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();

            $location->setCreatedAt(new DateTimeImmutable());
            $location->setUser($user);

            $entityManager->persist($location);
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_location');
        }

        return $this->render('admin/location/edit.html.twig', [
            'active' => 'location',
            'location' => $location,
            'form' => $form,
        ]);
    }
}
