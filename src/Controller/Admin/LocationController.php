<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Location;
use App\Form\LocationType;
use App\Repository\LocationRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_FOUNDER')]
class LocationController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return AdminNavigationConfig::single(
            section: 'System',
            label: 'Locations',
            route: 'app_admin_location',
            active: 'location',
            linkRole: 'ROLE_FOUNDER',
        );
    }

    public function __construct(
        private readonly LocationRepository $repo,
    ) {}

    #[Route('/admin/locations', name: 'app_admin_location')]
    public function list(): Response
    {
        return $this->render('admin/location/list.html.twig', [
            'active' => 'location',
            'locations' => $this->repo->findAll(),
        ]);
    }

    #[Route('/admin/locations/{id}/edit', name: 'app_admin_location_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Location $location, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(LocationType::class, $location);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Location updated successfully');

            return $this->redirectToRoute('app_admin_location');
        }

        return $this->render('admin/location/edit.html.twig', [
            'active' => 'location',
            'location' => $location,
            'form' => $form,
        ]);
    }

    #[Route('/admin/locations/add', name: 'app_admin_location_add', methods: ['GET', 'POST'])]
    public function add(Request $request, EntityManagerInterface $entityManager): Response
    {
        $location = new Location();
        $form = $this->createForm(LocationType::class, $location);
        $form->remove('createdAt');
        $form->remove('user');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();

            $location->setCreatedAt(new DateTimeImmutable());
            $location->setUser($user);

            $entityManager->persist($location);
            $entityManager->flush();

            $this->addFlash('success', 'Location created successfully');

            return $this->redirectToRoute('app_admin_location');
        }

        return $this->render('admin/location/edit.html.twig', [
            'active' => 'location',
            'location' => $location,
            'form' => $form,
        ]);
    }
}
