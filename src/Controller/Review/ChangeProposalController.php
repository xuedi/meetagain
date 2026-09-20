<?php declare(strict_types=1);

namespace App\Controller\Review;

use App\Controller\AbstractController;
use App\Entity\ChangeProposal;
use App\Entity\User;
use App\Form\BallotTermsType;
use App\Review\ChangeProposalException;
use App\Review\ChangeProposalService;
use App\Review\FieldBallotService;
use Module\Ballot\Contract\BallotView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/review')]
#[IsGranted('ROLE_USER')]
final class ChangeProposalController extends AbstractController
{
    public function __construct(
        private readonly ChangeProposalService $service,
        private readonly FieldBallotService $fieldBallots,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('/proposals/{targetType}/{targetId}', name: 'app_review_proposals', requirements: ['targetId' => '\d+'], methods: ['GET'])]
    public function proposals(string $targetType, int $targetId, #[CurrentUser] User $user): Response
    {
        $targetLabel = $this->service->targetLabel($targetType, $targetId);
        if ($targetLabel === null) {
            throw $this->createNotFoundException();
        }

        $proposals = $this->service->pendingForTarget($targetType, $targetId);
        $canReview = $this->service->canReviewTarget($targetType, $targetId, $user);
        $ballots = $this->fieldBallots->unresolvedByField($targetType, $targetId, (int) $user->getId());
        $cards = [];
        $isProposerOfAny = false;
        foreach ($proposals as $proposal) {
            $isProposer = $proposal->getProposedBy()->getId() === $user->getId();
            $isProposerOfAny = $isProposerOfAny || $isProposer;
            $cards[] = [
                'proposal' => $proposal,
                'rows' => $this->withBallots($this->service->fieldRows($proposal), $ballots),
                'isProposer' => $isProposer,
            ];
        }

        if (!$canReview && !$isProposerOfAny) {
            throw $this->createNotFoundException();
        }

        return $this->render('review/proposals.html.twig', [
            'ballotTerms' => $canReview ? $this->createForm(BallotTermsType::class, null, [
                    'mode' => FieldBallotService::DEFAULT_MODE,
                    'notice' => $this->translator->trans('review.ballot_terms_notice'),
                ])->createView() : null,
            'targetLabel' => $targetLabel,
            'targetUrl' => $this->service->targetUrl($targetType, $targetId),
            'targetType' => $targetType,
            'targetId' => $targetId,
            'cards' => $cards,
            'canReview' => $canReview,
        ]);
    }

    #[Route('/proposals/{targetType}/{targetId}/ballot', name: 'app_review_field_ballot_open', requirements: ['targetId' => '\d+'], methods: ['POST'])]
    public function openBallot(Request $request, string $targetType, int $targetId, #[CurrentUser] User $user): Response
    {
        $this->assertMayReview($request, $targetType, $targetId, $user);

        try {
            $this->fieldBallots->open($targetType, $targetId, $request->request->getString('field'), $user, $request->request->all('ballot_terms'));
            $this->addFlash('success', 'review.flash_ballot_opened');
        } catch (ChangeProposalException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToProposalsOf($targetType, $targetId);
    }

    #[Route(
        '/proposals/{targetType}/{targetId}/confirm/{ballotId}',
        name: 'app_review_field_ballot_confirm',
        requirements: ['targetId' => '\d+', 'ballotId' => '\d+'],
        methods: ['POST'],
    )]
    public function confirmBallot(Request $request, string $targetType, int $targetId, int $ballotId, #[CurrentUser] User $user): Response
    {
        $this->assertMayReview($request, $targetType, $targetId, $user);

        try {
            $this->fieldBallots->confirm($ballotId, $user);
            $this->addFlash('success', 'review.flash_ballot_confirmed');
        } catch (ChangeProposalException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToProposalsOf($targetType, $targetId);
    }

    #[Route('/proposal/{id}/apply/{field}', name: 'app_review_proposal_apply', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function applyField(Request $request, int $id, string $field, #[CurrentUser] User $user): Response
    {
        $proposal = $this->pendingProposal($request, $id);

        try {
            $this->service->applyField($proposal, $field, $user);
            $this->addFlash('success', 'review.flash_field_applied');
        } catch (ChangeProposalException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToProposals($proposal);
    }

    #[Route('/proposal/{id}/deny/{field}', name: 'app_review_proposal_deny', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function denyField(Request $request, int $id, string $field, #[CurrentUser] User $user): Response
    {
        $proposal = $this->pendingProposal($request, $id);

        try {
            $this->service->denyField($proposal, $field, $user);
            $this->addFlash('success', 'review.flash_field_denied');
        } catch (ChangeProposalException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToProposals($proposal);
    }

    #[Route('/proposal/{id}/withdraw', name: 'app_review_proposal_withdraw', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function withdraw(Request $request, int $id, #[CurrentUser] User $user): Response
    {
        $proposal = $this->pendingProposal($request, $id);

        try {
            $this->service->withdraw($proposal, $user);
            $this->addFlash('success', 'review.flash_withdrawn');
        } catch (ChangeProposalException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        $targetUrl = $this->service->targetUrl($proposal->getTargetType(), $proposal->getTargetId());
        if ($targetUrl !== null) {
            return $this->redirect($targetUrl);
        }

        return $this->redirectToProposals($proposal);
    }

    private function assertMayReview(Request $request, string $targetType, int $targetId, User $user): void
    {
        if (!$this->isCsrfTokenValid('field_ballot' . $targetType . $targetId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$this->service->canReviewTarget($targetType, $targetId, $user)) {
            throw $this->createAccessDeniedException();
        }
    }

    /**
     * @param  list<array<string, mixed>>            $rows
     * @param  array<string, BallotView>             $ballots
     * @return list<array<string, mixed>>
     */
    private function withBallots(array $rows, array $ballots): array
    {
        foreach ($rows as $index => $row) {
            $ballot = $ballots[$row['field']] ?? null;
            $rows[$index]['ballot'] = $ballot;
            $rows[$index]['ballotState'] = $ballot === null ? null : $this->fieldBallots->stateOf($ballot);
        }

        return $rows;
    }

    private function redirectToProposalsOf(string $targetType, int $targetId): Response
    {
        return $this->redirectToRoute('app_review_proposals', ['targetType' => $targetType, 'targetId' => $targetId]);
    }

    private function pendingProposal(Request $request, int $id): ChangeProposal
    {
        if (!$this->isCsrfTokenValid('change_proposal' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $proposal = $this->service->get($id);
        if ($proposal === null) {
            throw $this->createNotFoundException();
        }

        return $proposal;
    }

    private function redirectToProposals(ChangeProposal $proposal): Response
    {
        return $this->redirectToRoute('app_review_proposals', [
            'targetType' => $proposal->getTargetType(),
            'targetId' => $proposal->getTargetId(),
        ]);
    }
}
