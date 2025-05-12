<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Location;
use App\Form\LocationType;
use App\Repository\LocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminLocationController extends AbstractController
{
    #[Route('/admin/location/', name: 'app_admin_location')]
    public function locationList(LocationRepository $repo): Response
    {
        return $this->render('admin/location/list.html.twig', [
            'locations' => $repo->findAll(),
        ]);
    }
    #[Route('/admin/location/{id}', name: 'app_admin_location_edit', methods: ['GET', 'POST'])]
    public function locationEdit(Request $request, Location $location, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(LocationType::class, $location);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_location');
        }

        return $this->render('admin/location/edit.html.twig', [
            'location' => $location,
            'form' => $form,
        ]);
    }
}
