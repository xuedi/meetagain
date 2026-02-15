<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminLink;
use App\Entity\Location;
use App\Enum\EntityAction;
use App\Form\LocationType;
use App\Repository\EventRepository;
use App\Repository\LocationRepository;
use App\Service\EntityActionDispatcher;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_FOUNDER'), Route('/admin/locations')]
class LocationController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(section: 'System', links: [
            new AdminLink(label: 'Locations', route: 'app_admin_location', active: 'location', role: 'ROLE_FOUNDER'),
        ]);
    }

    public function __construct(
        private readonly LocationRepository $repo,
        private readonly EventRepository $eventRepo,
        private readonly EntityActionDispatcher $entityActionDispatcher,
    ) {}

    #[Route('', name: 'app_admin_location')]
    public function list(): Response
    {
        return $this->render('admin/location/list.html.twig', [
            'active' => 'location',
            'locations' => $this->repo->findAll(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_location_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Location $location, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(LocationType::class, $location);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->entityActionDispatcher->dispatch(EntityAction::UpdateLocation, $location->getId());

            $this->addFlash('success', 'Location updated successfully');

            return $this->redirectToRoute('app_admin_location');
        }

        // Get events using this location
        $eventsUsingLocation = $this->eventRepo->findBy(['location' => $location]);

        return $this->render('admin/location/edit.html.twig', [
            'active' => 'location',
            'location' => $location,
            'form' => $form,
            'events_using_location' => $eventsUsingLocation,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_location_delete', methods: ['GET'])]
    public function delete(Location $location, EntityManagerInterface $entityManager): Response
    {
        // Check if location is used in any events
        $eventsUsingLocation = $this->eventRepo->findBy(['location' => $location]);
        if (count($eventsUsingLocation) > 0) {
            $this->addFlash(
                'error',
                'Cannot delete location. It is currently used in ' . count($eventsUsingLocation) . ' event(s).',
            );

            return $this->redirectToRoute('app_admin_location_edit', ['id' => $location->getId()]);
        }

        $locationId = $location->getId();
        $entityManager->remove($location);
        $entityManager->flush();

        $this->entityActionDispatcher->dispatch(EntityAction::DeleteLocation, $locationId);

        $this->addFlash('success', 'Location deleted successfully');

        return $this->redirectToRoute('app_admin_location');
    }

    #[Route('/add', name: 'app_admin_location_add', methods: ['GET', 'POST'])]
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

            $this->entityActionDispatcher->dispatch(EntityAction::CreateLocation, $location->getId());

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
