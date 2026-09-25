<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\SecurityEventType;
use App\Form\ItemReportType;
use App\Item\Report\ReportService;
use App\Service\Security\SecurityService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/report/item')]
final class ItemReportController extends AbstractController
{
    public function __construct(
        private readonly ReportService $reportService,
        #[Autowire(service: 'limiter.item_report')]
        private readonly RateLimiterFactoryInterface $itemReportLimiter,
        private readonly SecurityService $securityService,
    ) {}

    #[Route('/sent', name: 'app_report_item_sent', methods: ['GET'])]
    public function sent(): Response
    {
        return $this->render('report/item_sent.html.twig');
    }

    #[Route('/{type}/{id}', name: 'app_report_item', requirements: ['type' => '[a-z][a-z0-9_]{0,63}', 'id' => '\d+'], methods: ['GET', 'POST'])]
    public function report(string $type, int $id, Request $request): Response
    {
        $itemLabel = $this->reportService->visibleItemLabel($type, $id);
        if ($itemLabel === null) {
            throw $this->createNotFoundException();
        }

        $user = $this->getUser();
        $member = $user instanceof User ? $user : null;
        $form = $this->createForm(ItemReportType::class, null, [
            'member_name' => $member?->getName(),
            'member_email' => $member?->getEmail(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$this->itemReportLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            $this->securityService->event(SecurityEventType::RateLimit, $request, ['limiter' => 'item_report']);

            return $this->render(
                'rate_limited.html.twig',
                ['message' => 'item_report.rate_limited_message'],
                new Response('', Response::HTTP_TOO_MANY_REQUESTS),
            );
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $this->reportService->create(
                itemType: $type,
                itemId: $id,
                itemLabel: $itemLabel,
                reason: $form->get('reason')->getData(),
                relationship: $form->get('relationship')->getData(),
                explanation: trim((string) $form->get('explanation')->getData()),
                notifierName: $member?->getName() ?? trim((string) $form->get('name')->getData()),
                notifierEmail: $member?->getEmail() ?? trim((string) $form->get('email')->getData()),
                reporter: $member,
                locale: $request->getLocale(),
            );

            return $this->redirectToRoute('app_report_item_sent');
        }

        return $this->render('report/item.html.twig', [
            'form' => $form,
            'itemLabel' => $itemLabel,
            'itemPath' => $this->reportService->itemPath($type, $id),
        ]);
    }
}
