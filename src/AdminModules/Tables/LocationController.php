<?php declare(strict_types=1);

namespace App\AdminModules\Tables;

use App\Entity\Location;
use App\Form\LocationType;
use App\Repository\LocationRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class LocationController extends AbstractController
{
    public function __construct(
        private readonly LocationRepository $repo,
    ) {}

    public function locationList(): Response
    {
        return $this->render('admin_modules/tables/location_list.html.twig', [
            'active' => 'location',
            'locations' => $this->repo->findAll(),
        ]);
    }

    public function locationEdit(Request $request, Location $location, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(LocationType::class, $location);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_location');
        }

        return $this->render('admin_modules/tables/location_edit.html.twig', [
            'active' => 'location',
            'location' => $location,
            'form' => $form,
        ]);
    }

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

        return $this->render('admin_modules/tables/location_edit.html.twig', [
            'active' => 'location',
            'location' => $location,
            'form' => $form,
        ]);
    }
}
