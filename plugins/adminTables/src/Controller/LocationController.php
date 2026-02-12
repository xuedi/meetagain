<?php declare(strict_types=1);

namespace Plugin\AdminTables\Controller;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Entity\Location;
use App\Repository\LocationRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\AdminTables\Form\LocationType;
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
            section: 'Tables',
            label: 'menu_admin_location',
            route: 'app_admin_location',
            active: 'location',
        );
    }

    public function __construct(
        private readonly LocationRepository $repo,
    ) {}

    #[Route('/admin/location', name: 'app_admin_location')]
    public function locationList(): Response
    {
        return $this->render('@AdminTables/tables/location_list.html.twig', [
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

        return $this->render('@AdminTables/tables/location_edit.html.twig', [
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
            $user = $this->getUser();

            $location->setCreatedAt(new DateTimeImmutable());
            $location->setUser($user);

            $entityManager->persist($location);
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_location');
        }

        return $this->render('@AdminTables/tables/location_edit.html.twig', [
            'active' => 'location',
            'location' => $location,
            'form' => $form,
        ]);
    }
}
