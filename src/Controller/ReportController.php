<?php declare(strict_types=1);

namespace App\Controller;

use App\Activity\ActivityService;
use App\Activity\Messages\ReportedImage;
use App\Entity\ImageReport;
use App\Enum\SecurityEventType;
use App\Form\ReportImageType;
use App\Form\ReportSubjectType;
use App\Moderation\ReportService;
use App\Service\Media\ImageService;
use App\Service\Security\SecurityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER'), Route('/report')]
final class ReportController extends AbstractController
{
    public function __construct(
        private readonly ActivityService $activityService,
        private readonly ImageService $imageService,
        private readonly EntityManagerInterface $em,
        private readonly ReportService $reportService,
        #[Autowire(service: 'limiter.moderation_report')]
        private readonly RateLimiterFactoryInterface $moderationReportLimiter,
        private readonly SecurityService $securityService,
    ) {}

    #[Route('/image/{id}', name: 'app_report_image')]
    public function index(Request $request, ?int $id = null): Response
    {
        $response = $this->getResponse();
        $user = $this->getAuthedUser();
        $image = $id === null ? null : $this->imageService->findImage($id);

        $form = $this->createForm(ReportImageType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $reason = $form->get('reported')->getData();
            $remarks = $form->get('remarks')->getData();

            $report = new ImageReport()
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

    #[Route('/success', name: 'app_report_success')]
    public function success(): Response
    {
        return $this->render('report/success.html.twig');
    }

    #[Route('/flag/sent', name: 'app_report_subject_sent', methods: ['GET'])]
    public function subjectSent(): Response
    {
        return $this->render('report/subject_sent.html.twig');
    }

    #[Route('/flag/{type}/{id}', name: 'app_report_subject', requirements: ['type' => '[a-z][a-z0-9_]{0,63}', 'id' => '\d+'], methods: ['GET', 'POST'])]
    public function subject(Request $request, string $type, int $id): Response
    {
        $user = $this->getAuthedUser();
        $snapshot = $this->reportService->describe($type, $id, $user);
        if ($snapshot === null) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(ReportSubjectType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$this->moderationReportLimiter->create((string) $user->getId())->consume()->isAccepted()) {
            $this->securityService->event(SecurityEventType::RateLimit, $request, ['limiter' => 'moderation_report']);

            return $this->render('rate_limited.html.twig', ['message' => 'report.rate_limited_message'], new Response('', Response::HTTP_TOO_MANY_REQUESTS));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $this->reportService->create(
                subjectType: $type,
                subjectId: $id,
                snapshot: $snapshot,
                reporter: $user,
                reason: $form->get('reason')->getData(),
                remarks: $form->get('remarks')->getData(),
            );

            return $this->redirectToRoute('app_report_subject_sent');
        }

        return $this->render('report/subject.html.twig', ['form' => $form]);
    }
}
