<?php declare(strict_types=1);

namespace App\Controller;

use App\Circulation\CirculationService;
use App\Circulation\DashboardService;
use App\Circulation\DashboardTabRegistry;
use App\Circulation\HandoverService;
use App\Enum\CirculationCopyStatus;
use App\Item\TypeRegistry;
use App\Repository\CirculationCopyRepository;
use App\Repository\CirculationHandoverRepository;
use App\Repository\CirculationRequestRepository;
use App\Service\TownHallService;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/circulation')]
#[IsGranted('ROLE_USER')]
final class CirculationController extends AbstractController
{
    private const string DEFAULT_TAB = 'shelf';
    private const array CORE_TABS = ['shelf', 'waiting', 'handovers', 'activity', 'stats', 'about'];

    public function __construct(
        private readonly CirculationService $circulation,
        private readonly DashboardService $dashboard,
        private readonly DashboardTabRegistry $tabs,
        private readonly HandoverService $handovers,
        private readonly CirculationCopyRepository $copies,
        private readonly CirculationRequestRepository $requests,
        private readonly CirculationHandoverRepository $handoverRepo,
        private readonly TypeRegistry $itemTypes,
        private readonly TownHallService $townHall,
    ) {}

    #[Route(
        '/{itemType}/{itemId}/donate',
        name: 'app_circulation_donate',
        requirements: ['itemType' => '[a-z_]+', 'itemId' => '\d+'],
        methods: ['POST'],
    )]
    public function donate(Request $request, string $itemType, int $itemId): RedirectResponse
    {
        $this->guardCsrf($request, 'app_circulation_donate' . $itemType . $itemId);
        $this->assertEnabled($itemType);

        try {
            $this->circulation->donate($itemType, $itemId, $this->getAuthedUser(), $request->request->getString('label'));
            $this->addFlash('success', 'circulation.flash_donated');
        } catch (RuntimeException $exception) {
            $this->addFlash('warning', $exception->getMessage());
        }

        return $this->redirectBack($request, $itemType);
    }

    #[Route(
        '/{itemType}/{itemId}/request',
        name: 'app_circulation_request',
        requirements: ['itemType' => '[a-z_]+', 'itemId' => '\d+'],
        methods: ['POST'],
    )]
    public function requestItem(Request $request, string $itemType, int $itemId): RedirectResponse
    {
        $this->guardCsrf($request, 'app_circulation_request' . $itemType . $itemId);
        $this->assertEnabled($itemType);

        try {
            $this->circulation->request($itemType, $itemId, $this->getAuthedUser());
            $this->addFlash('success', 'circulation.flash_requested');
        } catch (RuntimeException $exception) {
            $this->addFlash('warning', $exception->getMessage());
        }

        return $this->redirectBack($request, $itemType);
    }

    #[Route('/request/{id}/cancel', name: 'app_circulation_request_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelRequest(Request $request, int $id): RedirectResponse
    {
        $this->guardCsrf($request, 'app_circulation_request_cancel' . $id);

        $circulationRequest = $this->requests->find($id);
        if ($circulationRequest === null || $circulationRequest->getUser()->getId() !== $this->getAuthedUser()->getId()) {
            throw $this->createNotFoundException();
        }

        $this->circulation->cancelRequest($circulationRequest, $this->getAuthedUser());
        $this->addFlash('success', 'circulation.flash_request_cancelled');

        return $this->redirectBack($request, $circulationRequest->getItemType());
    }

    #[Route('/copy/{id}/finished', name: 'app_circulation_copy_finished', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markFinished(Request $request, int $id): RedirectResponse
    {
        $this->guardCsrf($request, 'app_circulation_copy_finished' . $id);

        $copy = $this->copies->find($id);
        if ($copy === null || !$copy->isHeldBy($this->getAuthedUser())) {
            throw $this->createNotFoundException();
        }

        try {
            $this->circulation->markFinished($copy, $this->getAuthedUser());
            $this->addFlash('success', 'circulation.flash_finished');
        } catch (RuntimeException $exception) {
            $this->addFlash('warning', $exception->getMessage());
        }

        return $this->redirectBack($request, $copy->getItemType());
    }

    #[Route('/copy/{id}/retire', name: 'app_circulation_copy_retire', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_STEWARD')]
    public function retire(Request $request, int $id): RedirectResponse
    {
        $this->guardCsrf($request, 'app_circulation_copy_retire' . $id);

        $copy = $this->copies->find($id);
        if ($copy === null) {
            throw $this->createNotFoundException();
        }

        $this->circulation->retire($copy, $this->getAuthedUser(), $request->request->getBoolean('lost'));
        $this->addFlash('success', 'circulation.flash_retired');

        return $this->redirectBack($request, $copy->getItemType());
    }

    #[Route('/handover/{id}', name: 'app_circulation_handover', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function handover(int $id): Response
    {
        $handover = $this->handoverRepo->find($id);
        if ($handover === null) {
            throw $this->createNotFoundException();
        }

        $seesAll = $this->isGranted('ROLE_STEWARD');
        if (!$seesAll && !$handover->isParticipant($this->getAuthedUser())) {
            throw $this->createNotFoundException();
        }

        $copy = $handover->getCopy();

        return $this->render('circulation/handover.html.twig', [
            'handover' => $handover,
            'copy' => $copy,
            'itemType' => $copy->getItemType(),
            'typeLabelKey' => $this->typeLabelKey($copy->getItemType()),
            'nextEvent' => $this->townHall->getUpcomingEvents(1)[0] ?? null,
            'isParticipant' => $handover->isParticipant($this->getAuthedUser()),
        ], $this->getResponse());
    }

    #[Route('/handover/{id}/confirm', name: 'app_circulation_handover_confirm', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function confirmHandover(Request $request, int $id): RedirectResponse
    {
        $this->guardCsrf($request, 'app_circulation_handover_confirm' . $id);

        $handover = $this->handoverRepo->find($id);
        if ($handover === null || !$handover->isParticipant($this->getAuthedUser())) {
            throw $this->createNotFoundException();
        }

        try {
            $this->handovers->confirm($handover, $this->getAuthedUser());
            $this->addFlash('success', 'circulation.flash_confirmed');
        } catch (RuntimeException $exception) {
            $this->addFlash('warning', $exception->getMessage());
        }

        return $this->redirectToRoute('app_circulation_handover', ['id' => $id]);
    }

    #[Route('/handover/{id}/cancel', name: 'app_circulation_handover_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelHandover(Request $request, int $id): RedirectResponse
    {
        $this->guardCsrf($request, 'app_circulation_handover_cancel' . $id);

        $handover = $this->handoverRepo->find($id);
        if ($handover === null || !$handover->isParticipant($this->getAuthedUser())) {
            throw $this->createNotFoundException();
        }

        $this->handovers->cancel($handover, $this->getAuthedUser());
        $this->addFlash('success', 'circulation.flash_handover_cancelled');

        return $this->redirectToRoute('app_circulation_dashboard', ['itemType' => $handover->getCopy()->getItemType()]);
    }

    #[Route('/{itemType}', name: 'app_circulation_dashboard', requirements: ['itemType' => '[a-z_]+'], methods: ['GET'])]
    public function dashboard(Request $request, string $itemType): Response
    {
        $this->assertEnabled($itemType);

        $viewer = $this->getAuthedUser();
        $seesAll = $this->isGranted('ROLE_STEWARD');
        $context = $this->circulation->getContext($itemType);
        $extraTabs = $this->tabs->forType($itemType, $context);
        $tab = $request->query->getString('tab', self::DEFAULT_TAB);
        $extraTab = $this->tabs->get($itemType, $context, $tab);
        if (!in_array($tab, self::CORE_TABS, true) && $extraTab === null) {
            $tab = self::DEFAULT_TAB;
        }

        $status = CirculationCopyStatus::tryFrom($request->query->getString('status'));

        return $this->render('circulation/dashboard.html.twig', [
            'itemType' => $itemType,
            'typeLabelKey' => $this->typeLabelKey($itemType),
            'context' => $context,
            'tab' => $tab,
            'coreTabs' => self::CORE_TABS,
            'extraTabs' => $extraTabs,
            'extraTabHtml' => $extraTab?->render($itemType, $context),
            'statusFilter' => $status,
            'statuses' => CirculationCopyStatus::cases(),
            'shelf' => $tab === 'shelf' ? $this->dashboard->getShelf($itemType, $status) : [],
            'waiting' => $tab === 'waiting' ? $this->dashboard->getWaiting($itemType, $viewer) : [],
            'openHandovers' => $tab === 'handovers' ? $this->dashboard->getOpenHandovers($itemType, $viewer, $seesAll) : [],
            'completedHandovers' => $tab === 'handovers' ? $this->dashboard->getCompletedHandovers($itemType, 25) : [],
            'activity' => $tab === 'activity' ? $this->dashboard->getActivity($itemType, $request->query->getInt('page', 1)) : null,
            'stats' => $tab === 'stats' ? $this->dashboard->getStats($itemType) : null,
            'about' => $tab === 'about' ? $this->dashboard->getMemberSummary($itemType, $viewer) : null,
            'viewer' => $viewer,
            'seesAll' => $seesAll,
        ], $this->getResponse());
    }

    private function assertEnabled(string $itemType): void
    {
        if (!$this->circulation->isEnabled($itemType)) {
            throw $this->createNotFoundException();
        }
    }

    private function typeLabelKey(string $itemType): ?string
    {
        return $this->itemTypes->providerForIncludingInactive($itemType)?->getLabelKey();
    }

    private function redirectBack(Request $request, string $itemType): RedirectResponse
    {
        $referer = $request->headers->get('referer');
        if ($referer !== null && $referer !== '' && parse_url($referer, PHP_URL_HOST) === $request->getHost()) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_circulation_dashboard', ['itemType' => $itemType]);
    }

    private function guardCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->request->getString('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
    }
}
