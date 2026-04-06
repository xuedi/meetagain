<?php declare(strict_types=1);

namespace App\Controller;

use App\Activity\ActivityService;
use App\Activity\Messages\ReportedImage;
use App\Entity\ImageReport;
use App\Form\ReportImageType;
use App\Repository\ImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ReportController extends AbstractController
{
    public function __construct(
        private readonly ActivityService $activityService,
        private readonly ImageRepository $repo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/report/image/{id}', name: 'app_report_image')]
    public function index(Request $request, ?int $id = null): Response
    {
        $response = $this->getResponse();
        $user = $this->getAuthedUser();
        $image = $this->repo->findOneBy(['id' => $id]);

        $form = $this->createForm(ReportImageType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $reason = $form->get('reported')->getData();
            $remarks = $form->get('remarks')->getData();

            $report = (new ImageReport())
                ->setImage($image)
                ->setReporter($user)
                ->setReason($reason)
                ->setRemarks($remarks !== '' ? $remarks : null);

            $this->em->persist($report);
            $this->em->flush();

            $meta = ['image_id' => $image->getId(), 'reason' => $reason->value];
            if ($remarks !== null && $remarks !== '') {
                $meta['remarks'] = $remarks;
            }
            $this->activityService->log(ReportedImage::TYPE, $user, $meta);

            return $this->redirectToRoute('app_report_success');
        }

        return $this->render(
            'report/image.html.twig',
            [
                'image' => $image,
                'form' => $form,
            ],
            $response,
        );
    }

    #[Route('/report/success', name: 'app_report_success')]
    public function success(): Response
    {
        return $this->render('report/success.html.twig');
    }
}
