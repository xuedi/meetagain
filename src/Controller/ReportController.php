<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\ActivityType;
use App\Form\ReportImageType;
use App\Repository\ImageRepository;
use App\Service\ActivityService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReportController extends AbstractController
{
    public function __construct(private readonly ActivityService $activityService)
    {
    }

    #[Route('/report/image/{id}', name: 'app_report_image')]
    public function index(Request $request, ImageRepository $repo, ?int $id = null): Response
    {
        $response = $this->getResponse();
        $user = $this->getAuthedUser();
        $image = $repo->findOneBy(['id' => $id]);

        $form = $this->createForm(ReportImageType::class, $image);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->activityService->log(ActivityType::ReportedImage, $user, [
                'image_id' => $image->getId(),
                'reason' => $form->get('reported')->getData()->value,
            ]);
            return $this->redirectToRoute('app_report_success');
        }

        return $this->render('report/image.html.twig', [
            'image' => $image,
            'form' => $form,
        ], $response);
    }

    #[Route('/report/success', name: 'app_report_success')]
    public function success(): Response
    {
        return $this->render('report/success.html.twig');
    }
}
